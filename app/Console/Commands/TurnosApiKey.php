<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TurnosApiKey extends Command
{
    protected $signature = 'turnos:api-key {nombre : Identificador del consumidor} {--revoke : Revocar su clave} {--output= : Archivo privado nuevo donde guardar la clave}';
    protected $description = 'Crear, rotar o revocar una clave de consulta de turnos (solo se almacenan hashes)';

    public function handle(): int
    {
        $name = $this->argument('nombre');
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $name)) {
            $this->error('Use un nombre de 1 a 64 letras, números, guiones o guiones bajos.');
            return self::FAILURE;
        }
        $output = $this->option('output');
        if (!$this->option('revoke') && (!$output || file_exists($output))) {
            $this->error('Indique --output con un archivo privado que todavía no exista.');
            return self::FAILURE;
        }
        $path = config('turnos_api.key_file');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        $handle = fopen($path, 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) {
            $this->error('No se pudo bloquear el archivo de claves.');
            return self::FAILURE;
        }
        try {
            $contents = stream_get_contents($handle);
            $keys = $contents === '' ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if ($this->option('revoke')) {
                unset($keys[$name]);
            } else {
                $token = 'turnos_'.bin2hex(random_bytes(32));
                $secret = fopen($output, 'x');
                if (!$secret) {
                    throw new \RuntimeException('No se pudo crear el archivo de entrega.');
                }
                chmod($output, 0600);
                fwrite($secret, $token.PHP_EOL);
                fclose($secret);
                $keys[$name] = hash('sha256', $token);
            }
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($keys, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            fflush($handle);
            chmod($path, 0600);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        $this->info($this->option('revoke') ? 'Clave revocada.' : 'Clave creada. Entréguela por un canal privado; no la incluya en instrucciones públicas.');
        return self::SUCCESS;
    }
}
