<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'morocare:create-admin';
    protected $description = 'Create an administrator interactively without default credentials';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Full name'), 'email' => strtolower(trim((string) $this->ask('Email'))),
            'password' => $this->secret('Password (at least 12 characters)'), 'role' => 'admin'];
        $validator = Validator::make($data, ['name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users', 'password' => 'required|string|min:12|max:128']);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) $this->error($error);
            return self::FAILURE;
        }
        if ($data['password'] !== $this->secret('Repeat password')) {
            $this->error('Passwords do not match.');
            return self::FAILURE;
        }
        User::create($data);
        $this->info('Administrator created.');
        return self::SUCCESS;
    }
}
