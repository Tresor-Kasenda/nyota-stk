<?php

namespace App\Http\Controllers;

use App\Infrastructure\PdoStore;
use App\Infrastructure\SqlBackup;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(private SqlBackup $backup, private PdoStore $store) {}

    public function create(): JsonResponse
    {
        $sql = $this->backup->export();
        $name = 'nyota_'.date('Y-M-d_').bin2hex(random_bytes(12)).'.sql';
        $path = 'backups/'.$name;
        Storage::disk('local')->put($path, $sql);
        $id = $this->store->insert('backups', ['file_name' => $name, 'file_url' => $path, 'date' => $this->store->now(), 'online' => 0]);
        $online = false;
        if (config('nyota.backup_url') && config('nyota.backup_token') && config('nyota.backup_key')) {
            $key = base64_decode(config('nyota.backup_key'), true);
            if (is_string($key) && strlen($key) === 32 && str_starts_with(config('nyota.backup_url'), 'https://')) {
                $encrypted = (new Encrypter($key, 'AES-256-CBC'))->encryptString($sql);
                try {
                    $result = Http::withToken(config('nyota.backup_token'))->connectTimeout(5)->timeout(30)->attach('file', $encrypted, $name.'.enc')->post(config('nyota.backup_url'), ['data' => '{"foldername":"nyota"}']);
                    $online = $result->successful() && $result->json('success') === 1;
                    if ($online) {
                        $this->store->update('backups', $id, ['online' => 1]);
                    }
                } catch (ConnectionException $error) {
                    report($error);
                }
            }
        }

        return response()->json(['status' => 1, 'msg' => 'Base des données sauvegardée avec succès', 'online_msg' => $online ? 'Base des données sauvegardée en ligne' : "La base des données n'a pas été sauvegardé en ligne", 'download' => route('backup.download', ['id' => $id])]);
    }

    public function restore(Request $request): JsonResponse
    {
        $request->validate(['dbfile' => 'required|file|max:20480|extensions:sql']);
        $this->backup->restore($request->file('dbfile')->getContent());
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 1, 'msg' => 'Base des données récupérée avec succès']);
    }

    public function download(Request $request): StreamedResponse
    {
        $row = $this->store->find('backups', (int) $request->route('id'));
        abort_unless($row && $row->file_url === 'backups/'.basename($row->file_name) && Storage::disk('local')->exists($row->file_url), 404);

        return Storage::disk('local')->download($row->file_url, basename($row->file_name));
    }
}
