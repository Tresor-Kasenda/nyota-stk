<?php

use App\Infrastructure\PdoStore;
use App\Infrastructure\SqlBackup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('nyota:admin {email} {--name=Administrateur} {--password-stdin}', function (PdoStore $store): int {
    $email = (string) $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Adresse email invalide');

        return 1;
    }
    $password = $this->option('password-stdin') ? rtrim(file_get_contents('php://stdin'), "\r\n") : $this->secret('Mot de passe (8 caractères minimum)');
    if (! is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
        $this->error('Le mot de passe doit contenir entre 8 et 72 caractères');

        return 1;
    }
    if ($store->query('SELECT id FROM admin WHERE email=?', [$email])) {
        $this->error('Ce compte existe déjà');

        return 1;
    }
    $store->insert('admin', ['first_name' => (string) $this->option('name'), 'last_name' => '', 'email' => mb_strtolower($email), 'mobile1' => '', 'mobile2' => '', 'role' => 'Super', 'password' => password_hash($password, PASSWORD_BCRYPT), 'created_on' => $store->now(), 'account_status' => '1', 'deleted' => '0', 'auth_version' => 0]);
    $this->info('Compte administrateur créé');

    return 0;
})->purpose('Créer le premier administrateur sans compte ni mot de passe de démonstration');

Artisan::command('nyota:import {file}', function (SqlBackup $backup): int {
    $file = (string) $this->argument('file');
    if (! is_file($file) || ! is_readable($file) || filesize($file) > 20 * 1024 * 1024) {
        $this->error('Fichier SQL inaccessible ou trop volumineux (20 Mo maximum)');

        return 1;
    }
    $backup->restore(file_get_contents($file));
    $this->info('Données importées, sessions historiques invalidées');

    return 0;
})->purpose('Importer les données historiques après migration du schéma');
