<?php

namespace NFePHP\DA\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../examples/gerar.php';

/**
 * Smoke test: cada XML fictício de examples/tipos/ gera um PDF válido, com a
 * classe certa e sem nenhum Warning/Notice/Deprecated nem saída antes do %PDF-
 * (qualquer texto antes do cabeçalho corrompe o PDF entregue ao navegador).
 */
class ExemplosTest extends TestCase
{
    /** Prefixo do arquivo => classe esperada. */
    private const CLASSES = [
        'nfe_reforma_' => 'DanfeRtc',
        'nfe_' => 'Danfe',
        'nfce_' => 'Danfce',
        'cteos_' => 'DacteOSV3',
        'cte_' => 'Dacte',
        'mdfe_' => 'Damdfe',
        'cce_' => 'Daevento',
    ];

    public static function exemplos(): array
    {
        $casos = [];
        foreach (glob(__DIR__ . '/../examples/tipos/*.xml') as $arquivo) {
            $casos[basename($arquivo, '.xml')] = [$arquivo];
        }
        return $casos;
    }

    public function testHaExemplosDeTodosOsTipos(): void
    {
        $classes = [];
        foreach (self::exemplos() as [$arquivo]) {
            $classes[exemploClasse(file_get_contents($arquivo))] = true;
        }
        foreach (array_unique(array_values(self::CLASSES)) as $classe) {
            $this->assertArrayHasKey($classe, $classes, "Sem exemplo para $classe");
        }
    }

    /**
     * @dataProvider exemplos
     */
    public function testGeraPdfSemAvisos(string $arquivo): void
    {
        $xml = file_get_contents($arquivo);
        $nome = basename($arquivo, '.xml');

        $esperada = null;
        foreach (self::CLASSES as $prefixo => $classe) {
            if (strpos($nome, $prefixo) === 0) {
                $esperada = $classe;
                break;
            }
        }
        $this->assertNotNull($esperada, "Prefixo sem classe esperada: $nome");
        $this->assertSame($esperada, exemploClasse($xml));

        $avisos = [];
        set_error_handler(function ($nivel, $msg, $arq, $linha) use (&$avisos) {
            // Aviso de dependência (ex.: E_USER_DEPRECATED do tc-lib-barcode 1.x) não é desta lib.
            if ($nivel === E_USER_DEPRECATED && strpos(str_replace("\\", "/", $arq), "/vendor/") !== false) {
                return true;
            }
            $avisos[] = sprintf('[%d] %s (%s:%d)', $nivel, $msg, basename($arq), $linha);
            return true;
        });
        $nivelAnterior = error_reporting(E_ALL);
        ob_start();
        try {
            $pdf = exemploPdf($xml);
        } finally {
            $saida = ob_get_clean();
            error_reporting($nivelAnterior);
            restore_error_handler();
        }

        $this->assertSame([], $avisos, 'Avisos do PHP ao gerar o PDF');
        $this->assertSame('', $saida, 'Saída impressa durante a geração');
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', substr($pdf, -64));
    }
}
