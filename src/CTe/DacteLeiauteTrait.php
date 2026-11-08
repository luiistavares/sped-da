<?php

namespace NFePHP\DA\CTe;

/**
 * PATCH 17 (consultadanfe, 2026-11-08): texto que cabe na caixa e folhas de
 * continuação sem perder dado, para o DACTE (Dacte) e o DACTE OS (DacteOSV3).
 *
 * Regras (MOC 4.00, Anexo II — Manual do DACTE, §2.3 e §2.12):
 *  - o conteúdo de um campo pode ocupar mais de uma linha, desde que legível;
 *  - o que não couber na 1ª folha vai para folhas adicionais, com o mesmo
 *    cabeçalho e as mesmas colunas, numeradas (FL n/total).
 *
 * Nada aqui inventa dado: só reorganiza o que vem do XML. O piso de fonte é o
 * mesmo do Patch 12 (4,5 pt); o que não cabe nem no piso volta como "sobra"
 * para o chamador mandar para a continuação — nunca é cortado em silêncio.
 */
trait DacteLeiauteTrait
{
    /** @var float piso de fonte legível (pt) */
    protected $fonteMinima = 4.5;
    /** @var list<string> campos que não couberam no próprio quadro (vão às observações) */
    protected $excedentes = [];
    /** @var list<list<string>> linhas de documentos originários para a continuação */
    protected $docsPendentes = [];
    /** @var list<list<string>> componentes do valor para a continuação */
    protected $compPendentes = [];
    /** @var array<string,string> textos (título => texto) para a continuação */
    protected $textosPendentes = [];
    /** @var bool 1ª passada (planejamento) em andamento */
    protected $planejando = false;
    /** @var bool 2ª passada: desenha de verdade, inclusive a continuação */
    protected $desenhando = false;
    /** @var string título do quadro de documentos (CT-e: originários; CT-e OS: referenciados) */
    protected $tituloDocs = 'DOCUMENTOS ORIGINÁRIOS';
    /** @var int total de folhas do DACTE */
    protected $totalFolhas = 1;

    /** Mesmo tratamento do pTextBox (Patch 11): entidades e Windows-1252. */
    protected function zTxtPdf($texto)
    {
        if (!is_string($texto)) {
            $texto = (string) $texto;
        }
        $texto = trim(preg_replace("/[ \t]*\r?\n[ \t]*/", "\n", $texto));
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    }

    /**
     * Desenha $texto dentro da caixa ($w × $h, em mm) sem sair dela: usa a maior
     * fonte (do tamanho pedido até o piso, em passos de 0,5 pt) em que o texto
     * quebrado cabe na altura. Se nem no piso couber, desenha o que cabe e
     * devolve o restante (UTF-8) para o chamador mandar à continuação.
     *
     * @param bool $desenhar false = só mede (planejamento das folhas)
     * @return string sobra ('' quando coube tudo)
     */
    protected function zCaixaTexto($x, $y, $w, $h, $texto, $aFont, $hAlign = 'L', $vAlign = 'T', $desenhar = true)
    {
        $t = $this->zTxtPdf($texto);
        if ($t === '') {
            return '';
        }
        $wUtil = max($w - 1, 1);
        $s = (float) $aFont['size'];
        $semEspaco = !preg_match('/\s/', $t); // número/código: reduz antes de partir
        while (true) {
            $this->pdf->setFont($aFont['font'], $aFont['style'], $s);
            $lh = $this->pdf->fontSize;
            $cabem = max(1, (int) floor(($h + 0.1) / $lh));
            // quebra parágrafo a parágrafo, guardando onde cada um termina
            $linhas = [];
            $fimPar = [];
            foreach (explode("\n", $t) as $par) {
                $q = $par;
                $this->pdf->wordWrap($q, $wUtil);
                foreach (explode("\n", $q) as $l) {
                    $linhas[] = $l;
                    $fimPar[] = false;
                }
                $fimPar[count($fimPar) - 1] = true;
            }
            $maxLinhas = $semEspaco ? 1 : $cabem;
            if (count($linhas) <= $maxLinhas || $s - 0.5 < $this->fonteMinima) {
                break;
            }
            $s -= 0.5;
        }
        $sobra = array_slice($linhas, $cabem);
        $linhas = array_slice($linhas, 0, $cabem);
        if ($desenhar) {
            $alt = $lh * count($linhas);
            $y1 = $y + $lh;
            if ($vAlign === 'C') {
                $y1 += ($h - $alt) / 2;
            } elseif ($vAlign === 'B') {
                $y1 = $y + $h - $alt + $lh - 0.5;
            }
            foreach ($linhas as $linha) {
                $linha = trim($linha);
                $comp = $this->pdf->getStringWidth($linha);
                if ($hAlign === 'C') {
                    $x1 = $x + ($w - $comp) / 2;
                } elseif ($hAlign === 'R') {
                    $x1 = $x + $w - $comp - 0.5;
                } else {
                    $x1 = $x + 0.5;
                }
                $this->pdf->text($x1, $y1, $linha);
                $y1 += $lh;
            }
        }

        if (!$sobra) {
            return '';
        }
        $resto = '';
        foreach ($sobra as $i => $l) {
            $resto .= trim($l) . ($fimPar[$cabem + $i] ? "\n" : ' ');
        }
        return mb_convert_encoding(trim($resto), 'UTF-8', 'Windows-1252');
    }

    /**
     * Campo de um quadro: cabe na caixa ou vai inteiro para as observações
     * (com o nome do quadro e do campo), marcado com "(...)" no quadro.
     */
    protected function zCampo($x, $y, $w, $h, $texto, $aFont, $rotulo, $hAlign = 'L', $vAlign = 'T', $secao = null)
    {
        if ($this->zCaixaTexto($x, $y, $w, $h, $texto, $aFont, $hAlign, $vAlign, false) === '') {
            $this->zCaixaTexto($x, $y, $w, $h, $texto, $aFont, $hAlign, $vAlign);
            return;
        }
        // maior prefixo (em palavras) que cabe junto com o aviso "(...)"
        $palavras = preg_split('/\s+/', trim($texto));
        $lo = 0;
        $hi = count($palavras) - 1;
        while ($lo < $hi) {
            $m = intdiv($lo + $hi + 1, 2);
            $tenta = implode(' ', array_slice($palavras, 0, $m)) . ' (...)';
            if ($this->zCaixaTexto($x, $y, $w, $h, $tenta, $aFont, $hAlign, $vAlign, false) === '') {
                $lo = $m;
            } else {
                $hi = $m - 1;
            }
        }
        $this->zCaixaTexto($x, $y, $w, $h, implode(' ', array_slice($palavras, 0, $lo)) . ' (...)', $aFont, $hAlign, $vAlign);
        $linha = $rotulo . ' (COMPLETO): ' . trim($texto);
        if ($secao === null) {
            $this->excedentes[] = $linha;
        } else { // quadro desenhado depois das observações: vai direto à continuação
            $this->textosPendentes[$secao] = trim(($this->textosPendentes[$secao] ?? '') . "\n" . $linha);
        }
    }

    /**
     * Texto livre de um quadro (observações, uso do emissor, fisco): cabe na
     * caixa ou continua na folha seguinte; nesse caso a última linha do quadro
     * avisa "CONTINUA NA PRÓXIMA FOLHA" (Anexo II, §2.1/§2.11).
     */
    protected function zTextoComContinuacao($x, $y, $w, $h, $texto, $aFont, $titulo)
    {
        if (trim($texto) === '') {
            return;
        }
        if ($this->zCaixaTexto($x, $y, $w, $h, $texto, $aFont, 'L', 'T', false) === '') {
            $this->zCaixaTexto($x, $y, $w, $h, $texto, $aFont);
            return;
        }
        $aviso = 2.2;
        $sobra = $this->zCaixaTexto($x, $y, $w, $h - $aviso, $texto, $aFont);
        $this->textosPendentes[$titulo] = $sobra;
        $fAviso = ['font' => $this->fontePadrao, 'size' => 5.5, 'style' => 'B'];
        $this->zCaixaTexto($x, $y + $h - $aviso, $w, $aviso, 'CONTINUA NA PRÓXIMA FOLHA', $fAviso, 'R');
    }

    /** Colunas de um componente na continuação (relativas a 1/3 da largura). */
    protected function zColunasComp()
    {
        return [['NOME', 0, 0.5], ['VALOR', 0.5, 0.5]];
    }

    /**
     * Componentes do valor da prestação (vPrest/Comp): 3 por linha, como no
     * original; 4 linhas de 4 mm (12) ou 6 de 3 mm (18); o resto vai para a
     * continuação. Só os Comp de vPrest (infGTVe/Comp do CT-e OS é outro grupo).
     */
    protected function zComponentes($x, $yQuadro, $yIni, $w)
    {
        $comps = [];
        if (!empty($this->vPrest)) {
            foreach ($this->vPrest->childNodes as $c) {
                if ($c->nodeType === XML_ELEMENT_NODE && $c->localName === 'Comp') {
                    $comps[] = [
                        $this->getTagValue($c, 'xNome'),
                        number_format((float) $this->getTagValue($c, 'vComp'), 2, ',', '.'),
                    ];
                }
            }
        }
        $passo = count($comps) > 12 ? 3 : 4;
        $linhas = (int) floor(($yQuadro + 25 - 2.6 - $yIni) / $passo) + 1;
        $cabem = 3 * $linhas;
        $this->compPendentes = array_slice($comps, $cabem);
        $f = $this->formatPadrao;
        foreach (array_slice($comps, 0, $cabem) as $i => [$nome, $valor]) {
            $col = $i % 3;
            $yl = $yIni + intdiv($i, 3) * $passo;
            $xc = $x + $col * $w * 0.28;
            $this->zCaixaTexto($xc, $yl, $w * 0.14 - 0.3, $passo, $nome, $f);
            $this->zCaixaTexto($xc + $w * 0.14, $yl, $w * 0.14 - 1.2, $passo, $valor, $f);
        }
    }

    /** Chave de acesso em 11 blocos de 4 (MOC 4.00, Anexo II, §2.4). */
    protected function zChaveEmBlocos($chave)
    {
        return trim(chunk_split($chave, 4, ' '));
    }

    /** Endereço "xLgr, nro - xCpl - xBairro" (Anexo II, §3). */
    protected function zEnderecoTexto($ender, $comBairro = true)
    {
        if (empty($ender)) {
            return '';
        }
        $t = $this->getTagValue($ender, 'xLgr');
        $nro = $this->getTagValue($ender, 'nro');
        $t .= $nro !== '' ? ', ' . $nro : '';
        $cpl = $this->getTagValue($ender, 'xCpl');
        $t .= $cpl !== '' ? ' - ' . $cpl : '';
        $bairro = $this->getTagValue($ender, 'xBairro');
        $t .= ($comBairro && $bairro !== '') ? ' - ' . $bairro : '';

        return $t;
    }

    /**
     * Folhas de continuação (Anexo II, §2.12): documentos originários e
     * componentes nas mesmas colunas da 1ª folha e, depois, os textos que não
     * couberam. Com $desenhar = false só conta as folhas.
     *
     * @param array $colDocs  colunas de um documento [[rótulo, x relativo, largura relativa], ...]
     * @param array $colComp  colunas de um componente
     * @return int número de folhas de continuação
     */
    protected function zContinuacao($desenhar, $colDocs = [], $colComp = [])
    {
        $secoes = [];
        if ($this->docsPendentes) {
            $secoes[] = ['tabela', $this->tituloDocs . ' - CONTINUAÇÃO', $colDocs, 2, 3.5, $this->docsPendentes];
        }
        if ($this->compPendentes) {
            $secoes[] = ['tabela', 'COMPONENTES DO VALOR DA PRESTAÇÃO DO SERVIÇO - CONTINUAÇÃO', $colComp, 3, 3.5, $this->compPendentes];
        }
        foreach ($this->textosPendentes as $titulo => $texto) {
            if (trim($texto) !== '') {
                $secoes[] = ['texto', $titulo . ' - CONTINUAÇÃO', $texto];
            }
        }
        if (!$secoes) {
            return 0;
        }
        $x = 1;
        $w = $this->wPrint;
        $yIni = 71;
        $yMax = $this->hPrint - 5;
        $folhas = 1;
        $y = $yIni;
        $novaFolha = function () use (&$folhas, &$y, $yIni, $desenhar) {
            $folhas++;
            $y = $yIni;
            if ($desenhar) {
                $this->zNovaFolhaContinuacao();
            }
        };
        if ($desenhar) {
            $this->zNovaFolhaContinuacao();
        }
        $fTit = ['font' => $this->fontePadrao, 'size' => 6, 'style' => ''];
        foreach ($secoes as $sec) {
            if ($yMax - $y < 12) {
                $novaFolha();
            }
            if ($sec[0] === 'tabela') {
                [, $titulo, $cols, $porLinha, $passo, $itens] = $sec;
                $linhas = array_chunk($itens, $porLinha);
                while ($linhas) {
                    $cabem = (int) floor(($yMax - $y - 7.4) / $passo);
                    if ($cabem < 1) {
                        $novaFolha();
                        continue;
                    }
                    $bloco = array_splice($linhas, 0, $cabem);
                    $h = 6.4 + count($bloco) * $passo + 1;
                    if ($desenhar) {
                        $this->zTabelaContinuacao($x, $y, $w, $h, $titulo . ($linhas ? ' (CONTINUA NA PRÓXIMA FOLHA)' : ''), $cols, $porLinha, $passo, $bloco);
                    }
                    $y += $h + 1;
                    if ($linhas) {
                        $novaFolha();
                    }
                }
            } else {
                [, $titulo, $texto] = $sec;
                $fTexto = ['font' => $this->fontePadrao, 'size' => 7, 'style' => ''];
                while (trim($texto) !== '') {
                    $hDisp = $yMax - $y - 3.4;
                    if ($hDisp < 5) {
                        $novaFolha();
                        continue;
                    }
                    // mede quanto cabe; a caixa encolhe até o texto quando sobra espaço
                    $sobra = $this->zCaixaTexto($x, $y + 3.4, $w, $hDisp, $texto, $fTexto, 'L', 'T', false);
                    $n = $this->zContaLinhas($texto, $w, $fTexto, $hDisp);
                    $h = 3.4 + $n + 1;
                    if ($desenhar) {
                        $this->pTextBox($x, $y, $w, $h, $titulo . ($sobra !== '' ? ' (CONTINUA NA PRÓXIMA FOLHA)' : ''), $fTit, 'T', 'C', 1, '');
                        $this->pdf->line($x, $y + 3.4, $x + $w, $y + 3.4);
                        $this->zCaixaTexto($x, $y + 3.4, $w, $hDisp, $texto, $fTexto, 'L', 'T', true);
                    }
                    $y += $h + 1;
                    $texto = $sobra;
                    if ($sobra !== '') {
                        $novaFolha();
                    }
                }
            }
        }

        return $folhas;
    }

    /** Altura (mm) que o texto ocupa na caixa, limitada a $hMax. */
    protected function zContaLinhas($texto, $w, $aFont, $hMax)
    {
        $t = $this->zTxtPdf($texto);
        $this->pdf->setFont($aFont['font'], $aFont['style'], $aFont['size']);
        $lh = $this->pdf->fontSize;
        $this->pdf->wordWrap($t, max($w - 1, 1));
        $n = count(explode("\n", $t));

        return min($n * $lh, floor(($hMax + 0.3) / $lh) * $lh);
    }

    /** Tabela com $porLinha grupos de colunas por linha (mesmas colunas da 1ª folha). */
    protected function zTabelaContinuacao($x, $y, $w, $h, $titulo, $cols, $porLinha, $passo, $linhas)
    {
        $fTit = ['font' => $this->fontePadrao, 'size' => 6, 'style' => ''];
        $fDado = ['font' => $this->fontePadrao, 'size' => 7, 'style' => ''];
        $this->pTextBox($x, $y, $w, $h, $titulo, $fTit, 'T', 'C', 1, '');
        $this->pdf->line($x, $y + 3.4, $x + $w, $y + 3.4);
        $wGrupo = $w / $porLinha;
        for ($g = 1; $g < $porLinha; $g++) {
            $this->pdf->line($x + $g * $wGrupo, $y + 3.4, $x + $g * $wGrupo, $y + $h);
        }
        for ($g = 0; $g < $porLinha; $g++) {
            foreach ($cols as $c) {
                $this->zCaixaTexto($x + $g * $wGrupo + $c[1] * $wGrupo, $y + 3.4, $c[2] * $wGrupo, 3, $c[0], $fTit);
            }
        }
        $yl = $y + 6.4;
        foreach ($linhas as $linha) {
            foreach ($linha as $g => $item) {
                foreach ($cols as $k => $c) {
                    $this->zCaixaTexto($x + $g * $wGrupo + $c[1] * $wGrupo, $yl, $c[2] * $wGrupo, $passo, $item[$k] ?? '', $fDado);
                }
            }
            $yl += $passo;
        }
    }

    /** PATCH 17: quadro TOMADOR DO SERVIÇO comum ao DACTE e ao DACTE OS. */
    protected function zTomadorQuadro($x = 0, $y = 0)
    {
        // PATCH 17: cada valor na sua célula (antes o município passava por cima
        // da UF/CEP e o endereço saía da página); o que não couber vai às observações.
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $w = $maxW;
        $h = 10;
        $fRot = $this->formatPadrao;
        $fVal = $this->formatNegrito;
        $xMun = $x + $maxW * 0.60 - 1;
        $xUf = $x + $maxW * 0.85 - 1;
        $xCep = $x + $w - 19;
        $xIe = $x + 65;
        $xPais = $x + $w * 0.75 - 1;
        $xFone = $x + $w - 28;
        $xFim = $x + $w - 0.3;
        $this->pTextBox($x, $y, $w, $h, 'TOMADOR DO SERVIÇO', $fRot, 'T', 'L', 1, '');
        $this->pTextBox($xMun, $y, 16, 3, 'MUNICÍPIO', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($xUf, $y, 5, 3, 'UF', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($xCep, $y, 6, 3, 'CEP', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($x, $y + 3, 16, 3, 'ENDEREÇO', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($x, $y + 6, 13, 3, 'CNPJ/CPF', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($xIe, $y + 6, 28, 3, 'INSCRIÇÃO ESTADUAL', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($xPais, $y + 6, 7, 3, 'PAÍS', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($xFone, $y + 6, 8, 3, 'FONE', $fRot, 'T', 'L', 0, '');
        if (empty($this->toma)) {
            return;
        }
        $t = 'TOMADOR DO SERVIÇO';
        $ender = !empty($this->enderToma) && is_object($this->enderToma) ? $this->enderToma : $this->toma;
        $this->zCampo($x + 25, $y, $xMun - $x - 25.3, 3, $this->getTagValue($this->toma, "xNome"), $fVal, "$t - NOME");
        $this->zCampo($xMun + 12, $y, $xUf - $xMun - 12.3, 3, $this->getTagValue($ender, "xMun"), $fVal, "$t - MUNICÍPIO");
        $this->zCaixaTexto($xUf + 3.5, $y, $xCep - $xUf - 3.8, 3, $this->getTagValue($ender, "UF"), $fVal);
        $cep = $this->getTagValue($ender, "CEP");
        $this->zCaixaTexto($xCep + 5, $y, $xFim - $xCep - 5, 3, $cep !== '' ? $this->pFormat($cep, "#####-###") : '', $fVal);
        $this->zCampo($x + 13, $y + 3, $xFim - $x - 13, 3, $this->zEnderecoTexto($ender), $fVal, "$t - ENDEREÇO");
        $this->zCaixaTexto($x + 12, $y + 6, $xIe - $x - 12.3, 3, $this->zFormatCNPJCPF($this->toma), $fVal);
        $this->zCampo($xIe + 24.5, $y + 6, $xPais - $xIe - 24.8, 3, $this->getTagValue($this->toma, "IE"), $fVal, "$t - INSCRIÇÃO ESTADUAL");
        $pais = $this->getTagValue($ender, "xPais");
        $this->zCampo($xPais + 5, $y + 6, $xFone - $xPais - 5.3, 3, $pais !== '' ? $pais : 'BRASIL', $fVal, "$t - PAÍS");
        $this->zCaixaTexto($xFone + 6, $y + 6, $xFim - $xFone - 6, 3, $this->zFormatFone($this->toma), $fVal);
    }

    /** PATCH 17: USO EXCLUSIVO DO EMISSOR / RESERVADO AO FISCO (altura do quadro: $h). */
    protected function zDadosAdicQuadro($x, $y, $h)
    {
        // PATCH 17: o Anexo II (§3) põe ObsCont/xTexto em "USO EXCLUSIVO DO EMISSOR"
        // e ObsFisco/xTexto em "RESERVADO AO FISCO" — antes eram descartados. O
        // texto se ajusta ao quadro e o que não couber continua na folha seguinte.
        $wTotal = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $fTit = array('font' => $this->fontePadrao, 'size' => 6, 'style' => '');
        $y0 = $y + 3;
        $w = $this->wAdic;
        $this->pTextBox($x, $y0, $w, $h, 'USO EXCLUSIVO DO EMISSOR DO CT-E', $fTit, 'T', 'C', 1, '');
        $this->pdf->Line($x, $y0 + 3, $x + $w, $y0 + 3);
        // ObsCont corridos (" | "), para caber mais no quadro sem abrir folha
        $obs = [];
        foreach ($this->dom->getElementsByTagName('ObsCont') as $o) {
            $obs[] = trim($o->getAttribute('xCampo') . ': ' . $this->getTagValue($o, 'xTexto'));
        }
        $texto = trim($this->textoAdic . ($obs ? "\n" . implode(' | ', $obs) : ''));
        $this->zTextoComContinuacao($x, $y0 + 3.1, $w - 1, $h - 3.3, $texto, $this->formatPadrao, 'USO EXCLUSIVO DO EMISSOR DO CT-E');
        // RESERVADO AO FISCO
        $xf = $x + $w;
        $wf = $wTotal - $w;
        $this->pTextBox($xf, $y0, $wf, $h, 'RESERVADO AO FISCO', $fTit, 'T', 'C', 1, '');
        //1 – Normal; 2 – FS; 3 – SCAN; 4 – DPEC; 5 – FS-DA (contingências)
        $xJust = $this->getTagValue($this->ide, 'xJust', 'Justificativa: ');
        $dhCont = $this->getTagValue($this->ide, 'dhCont', ' Entrada em contingência : ');
        $texto = '';
        switch ($this->tpEmis) {
            case 2:
                $texto = 'CONTINGÊNCIA FS' . $dhCont . $xJust;
                break;
            case 3:
                $texto = 'CONTINGÊNCIA SCAN' . $dhCont . $xJust;
                break;
            case 4:
                $texto = 'CONTINGÊNCIA DPEC' . $dhCont . $xJust;
                break;
            case 5:
                $texto = 'CONTINGÊNCIA FSDA' . $dhCont . $xJust;
                break;
        }
        $obs = [];
        foreach ($this->dom->getElementsByTagName('ObsFisco') as $o) {
            $obs[] = trim($o->getAttribute('xCampo') . ': ' . $this->getTagValue($o, 'xTexto'));
        }
        $this->pdf->Line($xf, $y0 + 3, $xf + $wf, $y0 + 3);
        $texto = trim($texto . ($obs ? "\n" . implode(' | ', $obs) : ''));
        $this->zTextoComContinuacao($xf, $y0 + 3.1, $wf - 1, $h - 3.3, $texto, $this->formatPadrao, 'RESERVADO AO FISCO');
        return $y0 + $h;
    }

    protected function zValorOuVazio($v)
    {
        return $v === '' ? '' : number_format((float) $v, 2, ',', '.');
    }

    /**
     * PATCH 17: quadro simples de campos (rótulo 5 pt + valor 7 pt negrito),
     * $porLinha células por linha de 6,5 mm. Valor longo quebra/reduz e, se
     * não couber, vai inteiro às observações (zCampo).
     */
    protected function zQuadroCampos($x, $y, $titulo, $campos, $porLinha)
    {
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $linhas = (int) ceil(count($campos) / $porLinha);
        $h = 3.4 + $linhas * 6.5;
        $this->pTextBox($x, $y, $maxW, $h, $titulo, $this->formatPadrao, 'T', 'C', 1, '');
        $this->pdf->Line($x, $y + 3.4, $x + $maxW, $y + 3.4);
        $wc = $maxW / $porLinha;
        $fRot = array('font' => $this->fontePadrao, 'size' => 5, 'style' => '');
        foreach ($campos as $i => [$rotulo, $valor]) {
            $col = $i % $porLinha;
            $lin = intdiv($i, $porLinha);
            $xc = $x + $col * $wc;
            $yc = $y + 3.4 + $lin * 6.5;
            if ($col > 0) {
                $this->pdf->Line($xc, $yc, $xc, $yc + 6.5);
            }
            if ($lin > 0 && $col === 0) {
                $this->pdf->Line($x, $yc, $x + $maxW, $yc);
            }
            $this->zCaixaTexto($xc, $yc, $wc - 0.3, 2.2, $rotulo, $fRot);
            $this->zCampo($xc, $yc + 2.1, $wc - 0.3, 4.2, $valor, $this->formatNegrito, $rotulo, 'L', 'T', $titulo);
        }
        return $y + $h;
    }

    /**
     * PATCH 17: quadro genérico do modal — todos os campos-folha do grupo do
     * modal (rótulo = nome da tag) para modais sem quadro próprio no DACTE OS.
     */
    protected function zModalGenerico($x, $y, $titulo, $no)
    {
        if ($no === null) {
            return $y;
        }
        $campos = [];
        foreach ($no->getElementsByTagName('*') as $e) {
            if ($e->childElementCount === 0 && trim($e->nodeValue) !== '') {
                $campos[] = [strtoupper($e->localName), trim($e->nodeValue)];
            }
        }
        return $campos ? $this->zQuadroCampos($x, $y, $titulo, $campos, 4) : $y;
    }

}
