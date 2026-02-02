<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MotherAppService;
use App\Models\Agencia;

class SyncAgencias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-agencias {token? : Bearer Token de un usuario autorizado en la App Madre}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza el catálogo de agencias desde la App Madre, asignando códigos locales manualmente.';

    protected $motherService;

    public function __construct(MotherAppService $service)
    {
        parent::__construct();
        $this->motherService = $service;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = $this->argument('token');

        if (!$token) {
            $token = $this->secret('Ingrese su Token de Acceso (Bearer) de la App Madre:');
        }

        if (!$token) {
            $this->error('Se requiere un token para conectar con la App Madre.');
            return 1;
        }

        $this->info('Conectando con App Madre...');

        try {
            // Suponemos que la respuesta es [data => [...]] o [...] directo
            // Ajustar según formato real de API Madre
            $agenciasData = $this->motherService->getAgencias($token);

            // Si viene paginado o en wrapper 'data'
            $agencias = $agenciasData['data'] ?? $agenciasData;

            if (!is_array($agencias)) {
                $this->error('Formato de respuesta inesperado.');
                return 1;
            }

            $count = count($agencias);
            $this->info("Se encontraron {$count} agencias.");

            foreach ($agencias as $agenciaMadre) {
                $motherId = $agenciaMadre['id'];
                $nombre = $agenciaMadre['nombre'];
                $codigoMadre = $agenciaMadre['codigo'];

                $this->newLine();
                $this->line("Procesando: <fg=yellow>{$nombre}</> (ID: {$motherId}, Codigo Madre: {$codigoMadre})");

                // Verificar si ya existe localmente por mother_id
                $localAgencia = Agencia::where('mother_id', $motherId)->first();

                if ($localAgencia) {
                    $this->info("  -> Ya existe localmente con Codigo Process: <fg=green>{$localAgencia->codigoprocess}</>");

                    if ($this->confirm('  ¿Desea actualizar nombre/código madre?', true)) {
                        $localAgencia->update([
                            'nombre' => $nombre,
                            'codigo' => $codigoMadre,
                        ]);
                        $this->info("  -> Actualizado.");
                    }
                } else {
                    // No existe, pedir codigo manual
                    $processCode = $this->ask("  -> Ingrese el CODIGO PROCESS local para esta agencia:");

                    if (empty($processCode)) {
                        $this->warn("  -> Saltando agencia por falta de código.");
                        continue;
                    }

                    // Verificar colisión de PK local
                    if (Agencia::find($processCode)) {
                        $this->error("  -> Error: El código '{$processCode}' ya está en uso por otra agencia local.");
                        continue;
                    }

                    Agencia::create([
                        'codigoprocess' => $processCode,
                        'mother_id' => $motherId,
                        'codigo' => $codigoMadre,
                        'nombre' => $nombre,
                    ]);

                    $this->info("  -> <fg=green>Creada correctamente.</>");
                }
            }

            $this->newLine();
            $this->info('Sincronización finalizada.');

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
