<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:5000',
        ]);
        DB::table('contact_messages')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Your message has been received.'], 201);
    }

    public function index(Request $request)
    {
        \App\Services\ListFilters::validate($request);
        $query = DB::table('contact_messages')->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%')->orWhere('email', 'like', '%'.$request->input('q').'%')->orWhere('message', 'like', '%'.$request->input('q').'%')));

        return \App\Services\ListFilters::dates($query, $request)->latest('id')->paginate(25);
    }
}
