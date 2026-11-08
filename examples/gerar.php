<?php

/**
 * Gera o PDF de cada XML de exemplo em examples/tipos/ e grava em examples/pdf/.
 *
 * Uso (na raiz do repositório, depois do `composer install`):
 *
 *     php examples/gerar.php            # todos
 *     php examples/gerar.php nfe_       # só os que começam com "nfe_"
 *
 * Os XMLs são FICTÍCIOS (CNPJ alfanumérico 12ABC345000188, CPFs/CNPJs de teste,
 * assinatura "FICTICIA"). A classe é escolhida pelo conteúdo do XML:
 *
 *   NF-e 55 com IBSCBS/IS/IBSCBSTot/ISTot -> DanfeRtc (NT 2026.010)
 *   NF-e 55 sem esses grupos              -> Danfe (Modelo Clássico)
 *   NFC-e 65                              -> Danfce
 *   CT-e 57                               -> Dacte
 *   CT-e OS 67                            -> DacteOSV3
 *   MDF-e 58                              -> Damdfe
 *   Evento de NF-e (CC-e)                 -> Daevento
 *
 * Este arquivo também é incluído pelo teste (tests/ExemplosTest.php), que usa
 * exemploClasse() e exemploPdf(); o laço de gravação só roda pela linha de comando.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use NFePHP\DA\CTe\Dacte;
use NFePHP\DA\CTe\DacteOSV3;
use NFePHP\DA\MDFe\Damdfe;
use NFePHP\DA\NFe\Daevento;
use NFePHP\DA\NFe\Danfce;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\NFe\DanfeRtc;

/**
 * Classe que desenha o documento do XML (nome curto), ou null se não reconhecido.
 */
function exemploClasse(string $xml): ?string
{
    // CT-e e MDF-e primeiro: eles citam NF-e (<infNFe><chave>) nos documentos.
    $mod = preg_match('/<mod>(\d{2})<\/mod>/', $xml, $m) ? $m[1] : '';
    if (strpos($xml, '<infCte') !== false) {
        return $mod === '67' ? 'DacteOSV3' : 'Dacte';
    }
    if (strpos($xml, '<infMDFe') !== false) {
        return 'Damdfe';
    }
    if (preg_match('/<infNFe\b[^>]*\bId\s*=/', $xml)) {
        if ($mod === '65') {
            return 'Danfce';
        }
        // Grupos da Reforma Tributária (NT 2025.002): leiaute da NT 2026.010.
        return preg_match('/<(IBSCBSTot|ISTot|IBSCBS|IS)\b[^>]*>/', $xml) ? 'DanfeRtc' : 'Danfe';
    }
    if (strpos($xml, '<infEvento') !== false) {
        return 'Daevento';
    }
    return null;
}

/**
 * Desenha o XML e devolve o PDF como string.
 */
function exemploPdf(string $xml): string
{
    switch (exemploClasse($xml)) {
        case 'DanfeRtc':
            $da = new DanfeRtc($xml);
            $da->montaDANFE();
            return $da->render();
        case 'Danfe':
            $da = new Danfe($xml);
            $da->montaDANFE();
            return $da->render();
        case 'Danfce':
            $da = new Danfce($xml);
            $da->monta();
            return $da->render();
        case 'Dacte':
            $da = new Dacte($xml);
            $da->montaDACTE();
            return $da->render();
        case 'DacteOSV3':
            $da = new DacteOSV3($xml);
            $da->monta();
            return $da->render();
        case 'Damdfe':
            // Orientação/papel explícitos e mododebug 0 (o padrão 2 liga display_errors).
            $da = new Damdfe($xml, 'P', 'A4', '', 'I', '', '', 0);
            $da->buildMDFe();
            return $da->render();
        case 'Daevento':
            $da = new Daevento($xml);
            $da->montaDaEventoNFe();
            return $da->printDocument('', 'S');
    }
    throw new InvalidArgumentException('Tipo de documento não reconhecido');
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $filtro = $argv[1] ?? '';
    $saida = __DIR__ . '/pdf';
    if (!is_dir($saida)) {
        mkdir($saida, 0775, true);
    }
    $falhas = 0;
    foreach (glob(__DIR__ . '/tipos/*.xml') as $arquivo) {
        $nome = basename($arquivo, '.xml');
        if ($filtro !== '' && strpos($nome, $filtro) !== 0) {
            continue;
        }
        $xml = file_get_contents($arquivo);
        try {
            $pdf = exemploPdf($xml);
            file_put_contents("$saida/$nome.pdf", $pdf);
            printf("%-48s %-10s %7d bytes\n", $nome, exemploClasse($xml), strlen($pdf));
        } catch (Throwable $e) {
            $falhas++;
            fprintf(STDERR, "%-48s FALHOU: %s\n", $nome, $e->getMessage());
        }
    }
    exit($falhas > 0 ? 1 : 0);
}
