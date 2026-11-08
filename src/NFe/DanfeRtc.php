<?php

namespace NFePHP\DA\NFe;

/**
 * DANFE NF-e com a Reforma Tributária do Consumo (IBS, CBS e IS): NT 2026.010
 * v1.00 (01/10/2026, produção em 01/12/2026), campos da NT 2025.002 v1.53.
 *
 * Ponto de partida: `DanfeRtc` do PR nfephp-org/sped-da#686 (autor robsonrrr,
 * commits de rogeriobbvn-rolemak, ajustes de dynne), portado para a sped-da
 * 0.1.26 e alinhado ao texto da NT (rótulos, pareamento das alíquotas e
 * valores, campos opcionais). Licença
 * LGPL-3.0+/GPL-3.0+/MIT, como a sped-da. Depende do Patch 9 do Danfe
 * (alturas protegidas + calculeHeight()).
 *
 * Mantém o Modelo Clássico: canhoto, cabeçalho, destinatário, fatura,
 * transporte, ISSQN, dados adicionais, rodapé, marcas de homologação,
 * cancelada e denegada são os da Danfe. Redesenha só o que a NT muda: linha de
 * CRT/regime no cabeçalho, quadros de totais e a grade de itens. A descrição do
 * item é a do Clássico (pDescricaoProduto: lote, medicamento, infAdProd).
 *
 * Regra da NT (§4.4): valor ausente no XML fica em branco; nada é inventado,
 * inferido ou calculado.
 */
class DanfeRtc extends Danfe
{
    /** Altura de cada linha de tributo na faixa do item, em mm. */
    const LH = 2.6;

    /**
     * Mesmos parâmetros do Danfe clássico (orientação, papel, logo, destino,
     * pasta, fonte, mododebug), repassados como vieram.
     *
     * @param string $xml
     */
    public function __construct($xml, ...$opcoesClassico)
    {
        parent::__construct($xml, ...$opcoesClassico);
        $this->hCabecalho = 54;
        $this->hCabecalhoItens = 11;
        $this->hImposto = empty($this->dom) ? 18 : $this->alturaImpostoRtc();
    }

    /**
     * Cabeçalho do Clássico + linha com CRT e regime de apuração do IBS/CBS.
     * O regime fica em branco: a NT 2026.010 manda não imprimir até a tag ser
     * definida (a C21a tpRegimeIBSCBS da NT 2026.005 ainda não foi ligada a ela).
     */
    protected function pCabecalhoDANFE($x = 0, $y = 0, $pag = '1', $totPag = '1')
    {
        $y = parent::pCabecalhoDANFE($x, $y, $pag, $totPag);
        $maxW = $this->larguraUtil($pag);
        $h = 7;
        $wCrt = round($maxW / 2, 0);
        $crt = $this->getTagValue($this->emit, 'CRT');
        $this->caixa($x, $y, $wCrt, $h, 'CÓDIGO DO REGIME TRIBUTÁRIO', $this->rotuloCrt($crt), 8);
        $this->caixa($x + $wCrt, $y, $maxW - $wCrt, $h, 'TIPO DE REGIME DE APURAÇÃO DO IBS E DA CBS', '', 8);

        return $y + $h;
    }

    /**
     * Totais em três blocos (NT 2026.010 §4.2): produtos e nota, ICMS/IPI e IBS/CBS/IS.
     */
    protected function pImpostoDANFE($x, $y)
    {
        $w = $this->larguraUtil(1);
        $y = $this->tituloBloco($x, $y, $w, 'TOTAL DOS PRODUTOS E TOTAL DA NOTA');
        $y = $this->linhaTotais($x, $y, $w, [
            ['VALOR TOTAL DOS PRODUTOS', $this->textoNumero($this->ICMSTot, 'vProd')],
            ['VALOR DO FRETE', $this->textoNumero($this->ICMSTot, 'vFrete')],
            ['VALOR DO SEGURO', $this->textoNumero($this->ICMSTot, 'vSeg')],
            ['DESCONTO', $this->textoNumero($this->ICMSTot, 'vDesc')],
            ['OUTRAS DESPESAS', $this->textoNumero($this->ICMSTot, 'vOutro')],
            ['VALOR TOTAL DA NOTA', $this->textoNumero($this->ICMSTot, 'vNF')],
        ]);

        $y = $this->tituloBloco($x, $y, $w, 'TOTAL DO ICMS / IPI');
        $y = $this->linhaTotais($x, $y, $w, [
            ['BASE DE CÁLCULO DO ICMS', $this->textoNumero($this->ICMSTot, 'vBC')],
            ['VALOR DO ICMS', $this->textoNumero($this->ICMSTot, 'vICMS')],
            ['BASE DE CÁLCULO DO ICMS ST', $this->textoNumero($this->ICMSTot, 'vBCST')],
            ['VALOR DO ICMS ST', $this->textoNumero($this->ICMSTot, 'vST')],
            ['VALOR TOTAL DO IPI', $this->textoNumero($this->ICMSTot, 'vIPI')],
        ]);
        foreach (array_chunk($this->camposIcmsOpcionais(), 4) as $linha) {
            $y = $this->linhaTotais($x, $y, $w, $linha);
        }

        $ibs = $this->dom->getElementsByTagName('IBSCBSTot')->item(0);
        $isTot = $this->dom->getElementsByTagName('ISTot')->item(0);
        $y = $this->tituloBloco($x, $y, $w, 'TOTAL DO IBS / CBS / IS');
        $y = $this->linhaTotais($x, $y, $w, [
            ['VALOR DA CBS', $this->textoNumero($ibs, 'vCBS')],
            ['VALOR DO IBS UF', $this->textoNumero($ibs, 'vIBSUF')],
            ['VALOR DO IBS MUNICÍPIO', $this->textoNumero($ibs, 'vIBSMun')],
            ['VALOR DO IMPOSTO SELETIVO', $this->textoNumero($isTot, 'vIS')],
        ]);
        $mono = $this->camposMonofasico();
        if (!empty($mono)) {
            $y = $this->linhaTotais($x, $y, $w, $mono);
        }

        return $y;
    }

    /**
     * Grade em faixa: identificação do item e, à direita, bases, alíquotas e valores.
     */
    protected function pItensDANFE($x, $y, &$nInicio, $hmax, $pag = 0, $totpag = 0, $hCabecItens = 7)
    {
        $oldX = $x;
        $oldY = $y;
        if ($hmax < $hCabecItens + 1) {
            // página sem espaço nem para o cabeçalho da grade (muitas duplicatas +
            // dados adicionais longos): nada de caixa com altura negativa, que
            // jogaria os blocos seguintes por cima do transporte. Itens vão para a próxima.
            return $oldY;
        }
        $w = $this->larguraUtil($pag);
        $aFont = ['font' => $this->fontePadrao, 'size' => 7, 'style' => 'B'];
        $this->pTextBox($x, $y, $w, 4, 'DADOS DOS PRODUTOS / SERVIÇOS', $aFont, 'T', 'L', 0, '');
        $y += 3;
        $hmax += 1;
        $this->pTextBox($x, $y, $w, $hmax);
        $this->cabecalhoItens($x, $y, $w);
        $y += 7;

        $i = 0;
        $hUsado = $hCabecItens;
        foreach ($this->det as $det) {
            if ($i >= $nInicio) {
                $h = $this->calculeHeight($det, 0);
                $hUsado += $h;
                // nas páginas seguintes cabe ao menos um item (sem laço infinito); na 1ª, sem espaço, o item vai para a 2ª
                if (($hmax - $hUsado) < 1 && ($i > $nInicio || (int) $pag === 1)) {
                    $nInicio = $i;
                    break;
                }
                $this->faixaItem($oldX, $y, $w, $det);
                $y += $h;
                $this->qtdeItensProc++;
            }
            $i++;
        }

        return $oldY + $hmax;
    }

    /**
     * Altura da faixa do item, usada também na contagem de páginas (Patch 9).
     *
     * @param \DOMElement $item
     * @param float $w2 ignorado: a largura vem de colunasItem()
     * @return float
     */
    protected function calculeHeight($item, $w2)
    {
        return $this->medidaFaixa($item)['h'];
    }

    /**
     * Altura do quadro de totais: 3 títulos de 3 mm e linhas de 7 mm.
     *
     * @return int
     */
    protected function alturaImpostoRtc()
    {
        $linhas = 3 + (int) ceil(count($this->camposIcmsOpcionais()) / 4);
        if (!empty($this->camposMonofasico())) {
            $linhas++;
        }

        return 3 * 3 + $linhas * 7 + 1;
    }

    /**
     * Linha opcional do ICMS (NT 2026.010 §4.2): só os campos com valor diferente
     * de zero. O vFCP é obrigatório no ICMSTot e quase sempre 0,00; imprimir a
     * linha sempre ocuparia espaço sem informação.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function camposIcmsOpcionais()
    {
        $mapa = [
            'vFCP' => ['VALOR DO FCP', false],
            'vFCPST' => ['VALOR DO FCP RETIDO POR ST', false],
            'vICMSUFDest' => ['DIFAL NA UF DE DESTINO', false],
            'vFCPUFDest' => ['FCP NA UF DE DESTINO', false],
            'qBCMono' => ['BC DO ICMS MONOFÁSICO', true],
            'vICMSMono' => ['VALOR DO ICMS MONOFÁSICO', false],
            'qBCMonoReten' => ['BC DO ICMS MONOFÁSICO POR RETENÇÃO', true],
            'vICMSMonoReten' => ['VALOR DO ICMS MONOFÁSICO POR RETENÇÃO', false],
        ];
        $campos = [];
        foreach ($mapa as $tag => list($titulo, $quantidade)) {
            $no = empty($this->ICMSTot) ? null : $this->ICMSTot->getElementsByTagName($tag)->item(0);
            if (empty($no) || (float) $no->nodeValue == 0.0) {
                continue;
            }
            $valor = $quantidade ? $this->textoQuantidade($this->ICMSTot, $tag) : $this->textoNumero($this->ICMSTot, $tag);
            $campos[] = [$titulo, $valor];
        }

        return $campos;
    }

    /**
     * Segunda linha do bloco IBS/CBS/IS: só quando o grupo gMono (W57) existe;
     * as de retenção só quando as tags existem.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function camposMonofasico()
    {
        if (empty($this->dom)) {
            return [];
        }
        $tot = $this->dom->getElementsByTagName('IBSCBSTot')->item(0);
        $gMono = $tot ? $this->primeiroFilho($tot, 'gMono') : null;
        if (empty($gMono)) {
            return [];
        }
        $mapa = [
            'vIBSMono' => 'VALOR DO IBS MONOFÁSICO',
            'vCBSMono' => 'VALOR DA CBS MONOFÁSICA',
            'vIBSMonoReten' => 'VALOR DO IBS MONOFÁSICO POR RETENÇÃO',
            'vCBSMonoReten' => 'VALOR DA CBS MONOFÁSICA POR RETENÇÃO',
        ];
        $campos = [];
        foreach ($mapa as $tag => $titulo) {
            $no = $this->primeiroFilho($gMono, $tag);
            // as de retenção são obrigatórias no gMono (W59a/b), quase sempre 0,00:
            // a NT manda exibir só "quando houver retenção"
            $retencao = substr($tag, -5) === 'Reten';
            if ($no && (!$retencao || (float) $no->nodeValue != 0.0)) {
                $campos[] = [$titulo, $this->textoNumero($gMono, $tag)];
            }
        }

        return $campos;
    }

    protected function tituloBloco($x, $y, $w, $texto)
    {
        $aFont = ['font' => $this->fontePadrao, 'size' => 7, 'style' => 'B'];
        $this->pTextBox($x, $y, $w, 4, $texto, $aFont, 'T', 'L', 0, '');

        return $y + 3;
    }

    /**
     * @param array<int, array{0: string, 1: string}> $campos
     */
    protected function linhaTotais($x, $y, $w, array $campos)
    {
        $n = max(1, count($campos));
        $largura = $w / $n;
        $h = 7;
        $cursor = $x;
        foreach ($campos as $campo) {
            $this->caixa($cursor, $y, $largura, $h, $campo[0], $campo[1], 9);
            $cursor += $largura;
        }

        return $y + $h;
    }

    protected function caixa($x, $y, $w, $h, $titulo, $valor, $tamanhoValor)
    {
        $fontTitulo = ['font' => $this->fontePadrao, 'size' => 5, 'style' => ''];
        $fontValor = ['font' => $this->fontePadrao, 'size' => $tamanhoValor, 'style' => 'B'];
        $this->celula($x, $y, $w, $h, $titulo, $fontTitulo, 'T', 'L', 1, '');
        $this->celula($x, $y, $w, $h, $valor, $fontValor, 'B', 'R', 0, '');
    }

    /**
     * Cabeçalho da grade: uma linha de títulos, sem legenda (cada linha de
     * tributo traz o próprio rótulo na coluna TRIBUTO).
     */
    protected function cabecalhoItens($x, $y, $w)
    {
        $cols = $this->colunasItem($w);
        $aFont = ['font' => $this->fontePadrao, 'size' => 5, 'style' => 'B'];
        $h = 7;
        $crt = $this->getTagValue($this->emit, 'CRT');
        $labels = [
            'CÓDIGO',
            'DESCRIÇÃO DO PRODUTO / SERVIÇO',
            in_array($crt, ['1', '4'], true) ? 'CSOSN / CFOP' : 'CST / CFOP',
            'QTD / UN',
            'VLR UNIT',
            'VLR TOTAL',
            'TRIBUTO',
            'BASES DE CÁLCULO',
            'ALÍQUOTAS',
            'VALOR DOS TRIBUTOS',
        ];
        foreach ($cols as $i => $largura) {
            $this->celula($x, $y, $largura, $h, $labels[$i], $aFont, 'C', 'C', 1, '', false);
            $x += $largura;
        }
    }

    /**
     * Uma faixa por item: identificação à esquerda; à direita, uma linha por
     * tributo (rótulo em cinza + base, alíquota e valor alinhados à direita).
     */
    protected function faixaItem($x, $y, $w, $det)
    {
        $medida = $this->medidaFaixa($det);
        $cols = $this->colunasItem($w);
        $lh = self::LH;
        $hMiolo = $medida['hMiolo'];
        $prod = $det->getElementsByTagName('prod')->item(0);
        $aFont = ['font' => $this->fontePadrao, 'size' => 6, 'style' => ''];
        $aFontMiudo = ['font' => $this->fontePadrao, 'size' => 5, 'style' => ''];

        // divisórias de coluna; entre TRIBUTO e BASE não há (o rótulo é cabeça da linha)
        $cursor = $x;
        foreach ($cols as $i => $largura) {
            if ($i > 0 && $i !== 7) {
                $this->pdf->line($cursor, $y, $cursor, $y + $hMiolo);
            }
            $cursor += $largura;
        }

        $this->celula($x, $y, $cols[0], $lh * $medida['nCod'], $medida['codigo'], $aFont, 'T', 'L', 0, '', false);
        $xDesc = $x + $cols[0];
        $this->celula($xDesc, $y, $cols[1], $lh * $medida['nNome'], $medida['nome'], $aFont, 'T', 'L', 0, '', false);
        $this->celula($xDesc, $y + $hMiolo - $lh, $cols[1], $lh, $medida['rodape'], $aFontMiudo, 'B', 'L', 0, '');

        $xCst = $xDesc + $cols[1];
        $this->celula($xCst, $y, $cols[2], $lh, $this->cstItem($det), $aFont, 'T', 'C', 0, '');
        $this->celula($xCst, $y + $lh, $cols[2], $lh, $this->getTagValue($prod, 'CFOP'), $aFont, 'T', 'C', 0, '');

        $xQtd = $xCst + $cols[2];
        $un = $this->getTagValue($prod, 'uCom');
        $this->celulaNumero($xQtd, $y, $cols[3], $this->textoNumero($prod, 'qCom', 4), $aFont);
        $this->celulaNumero($xQtd, $y + $lh, $cols[3], $un, $aFont);

        $xUnit = $xQtd + $cols[3];
        $this->celulaNumero($xUnit, $y, $cols[4], $this->textoNumero($prod, 'vUnCom', 4), $aFont);
        $xTotal = $xUnit + $cols[4];
        $this->celulaNumero($xTotal, $y, $cols[5], $this->textoNumero($prod, 'vProd'), $aFont);

        $xTrib = $xTotal + $cols[5];
        $xBase = $xTrib + $cols[6];
        $xAliq = $xBase + $cols[7];
        $xValor = $xAliq + $cols[8];
        $aRotulo = ['font' => $this->fontePadrao, 'size' => 4.5, 'style' => ''];
        $wTrib = $cols[6] + $cols[7] + $cols[8] + $cols[9];
        foreach ($this->tributosItem($det) as $i => list($rotulo, $base, $aliq, $valor)) {
            $yLinha = $y + $i * $lh;
            if ($i > 0) {
                // fio cinza bem fino entre as linhas de tributo: guia o olho sem pesar
                $this->pdf->SetDrawColor(205, 205, 205);
                $this->pdf->SetLineWidth(0.05);
                $this->pdf->line($xTrib + 0.6, $yLinha + 0.15, $xTrib + $wTrib - 0.6, $yLinha + 0.15);
                $this->pdf->SetLineWidth(0.1);
                $this->pdf->SetDrawColor(0, 0, 0);
            }
            $this->pdf->SetTextColor(110, 110, 110);
            $this->celula($xTrib, $yLinha, $cols[6], $lh, $rotulo, $aRotulo, 'T', 'L');
            $this->pdf->SetTextColor(0, 0, 0);
            $this->celulaNumero($xBase, $yLinha, $cols[7], $base, $aFontMiudo);
            $this->celulaNumero($xAliq, $yLinha, $cols[8], $aliq, $aFontMiudo);
            $this->celulaNumero($xValor, $yLinha, $cols[9], $valor, $aFontMiudo);
        }

        $this->pdf->SetDrawColor(170, 170, 170);
        $this->pdf->dashedHLine($x, $y + $hMiolo + 0.4, $w, 0.1, 120);
        $this->pdf->SetDrawColor(0, 0, 0);
    }

    /**
     * Medidas da faixa desenhada por faixaItem(). A descrição é a do Clássico.
     *
     * @param \DOMElement $det
     * @return array{h: float, hMiolo: float, nNome: int, nCod: int, nome: string, codigo: string, rodape: string}
     */
    protected function medidaFaixa($det)
    {
        $cols = $this->colunasItem($this->larguraUtil(1));
        $aFont = ['font' => $this->fontePadrao, 'size' => 6, 'style' => ''];
        $lh = self::LH;
        $prod = $det->getElementsByTagName('prod')->item(0);
        // a descrição do Clássico já traz o veículo novo (textoVeiculo, Patch 18)
        $nome = $this->quebraPalavrasLongas($this->pDescricaoProduto($det), $cols[1] - 1.5, $aFont);
        $codigo = $this->quebraPalavrasLongas($this->getTagValue($prod, 'cProd'), $cols[0] - 1.5, $aFont);
        $nNome = max(1, (int) $this->pGetNumLines($nome, max(10, $cols[1] - 1), $aFont));
        $nCod = max(1, (int) $this->pGetNumLines($codigo, max(5, $cols[0] - 1), $aFont));
        // +1: linha [NCM] [cClassTrib]; 2: quantidade e unidade
        $hMiolo = max($nNome + 1, $nCod, count($this->tributosItem($det)), 2) * $lh;

        return [
            'h' => round($hMiolo + 1.0, 2),
            'hMiolo' => $hMiolo,
            'nNome' => $nNome,
            'nCod' => $nCod,
            'nome' => $nome,
            'codigo' => $codigo,
            'rodape' => $this->rodapeItem($det),
        ];
    }

    /**
     * O quebra-linha do FPDF só quebra em espaço: uma palavra mais larga que a
     * coluna (código sem separador, descrição colada) vazaria para a vizinha.
     * Aqui a palavra é partida em pedaços que cabem; a faixa cresce para caber tudo.
     */
    protected function quebraPalavrasLongas($texto, $largura, $aFont)
    {
        if ($texto === '' || $largura <= 0) {
            return $texto;
        }
        $this->pdf->SetFont($aFont['font'], $aFont['style'], $aFont['size']);
        $linhas = [];
        foreach (explode("\n", $texto) as $linha) {
            $palavras = [];
            foreach (explode(' ', $linha) as $palavra) {
                // mede como o FPDF vai desenhar (ISO-8859-1)
                if ($this->pdf->GetStringWidth(mb_convert_encoding($palavra, 'ISO-8859-1', 'UTF-8')) <= $largura) {
                    $palavras[] = $palavra;
                    continue;
                }
                $pedaco = '';
                foreach (preg_split('//u', $palavra, -1, PREG_SPLIT_NO_EMPTY) as $c) {
                    if ($pedaco !== '' && $this->pdf->GetStringWidth(mb_convert_encoding($pedaco . $c, 'ISO-8859-1', 'UTF-8')) > $largura) {
                        $palavras[] = $pedaco;
                        $pedaco = '';
                    }
                    $pedaco .= $c;
                }
                $palavras[] = $pedaco;
            }
            $linhas[] = implode(' ', $palavras);
        }

        return implode("\n", $linhas);
    }

    /**
     * Texto de célula que nunca passa da borda. O pTextBox do Clássico escreve
     * 0,5 mm para dentro da borda esquerda mas quebra/reduz na largura inteira,
     * então a linha mais larga vazava até 0,5 mm na célula vizinha. Aqui a borda
     * usa a largura inteira e o texto, 0,5 mm de margem de cada lado.
     */
    protected function celula($x, $y, $w, $h, $texto = '', $aFont = null, $vAlign = 'T', $hAlign = 'L', $borda = 0, $link = '', $force = true)
    {
        $aFont = $aFont ?: ['font' => $this->fontePadrao, 'size' => 6, 'style' => ''];
        if ($borda) {
            $this->pTextBox($x, $y, $w, $h, '', $aFont, 'T', 'L', 1, '');
        }
        if ($texto === '' || $texto === null) {
            return;
        }
        $this->pTextBox($x + 0.5, $y, $w - 1, $h, $texto, $aFont, $vAlign, $hAlign, 0, $link, $force);
    }

    /**
     * Número alinhado à direita: reduz a fonte até caber, sem passar da borda.
     */
    protected function celulaNumero($x, $y, $w, $texto, $aFont)
    {
        // o pTextBox reduz de 1 em 1 pt (6 → 5 → 4); aqui a redução é fina e para em 4,5 pt
        if ($texto !== '' && $texto !== null) {
            $this->pdf->SetFont($aFont['font'], $aFont['style'], $aFont['size']);
            $largura = $this->pdf->GetStringWidth(mb_convert_encoding((string) $texto, 'ISO-8859-1', 'UTF-8'));
            if ($largura > $w - 1.2) {
                $aFont['size'] = max(4.5, floor($aFont['size'] * ($w - 1.2) / $largura * 4) / 4);
            }
        }
        $this->celula($x, $y, $w, self::LH, $texto, $aFont, 'T', 'R');
    }

    /**
     * Larguras (somam $w): código, descrição, CST/CFOP, qtd/un, vlr unit,
     * vlr total, tributo, bases, alíquotas, valores.
     *
     * @param float $w
     * @return float[]
     */
    protected function colunasItem($w)
    {
        $partes = [0.06, 0.27, 0.05, 0.085, 0.08, 0.075, 0.06, 0.105, 0.075];
        $cols = [];
        $usado = 0;
        foreach ($partes as $parte) {
            $largura = round($w * $parte, 0);
            $cols[] = $largura;
            $usado += $largura;
        }
        $cols[] = $w - $usado;

        return $cols;
    }

    /**
     * Tributos do item, um por linha: [rótulo, base, alíquota, valor]. Mesmos
     * campos da NT 2026.010 §4.3 (bases, alíquotas e valores de ICMS, IBS UF,
     * IBS Mun, CBS, IS e IPI), mas cada linha diz de qual tributo é, em vez dos
     * pares sem rótulo do modelo (ICMS|CBS…), que confundem a leitura. Só entra
     * o tributo cujo grupo existe no item; dentro dele, tag ausente fica em branco.
     *
     * @param \DOMElement $det
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    protected function tributosItem($det)
    {
        $imposto = $det->getElementsByTagName('imposto')->item(0);
        $icms = $imposto ? $imposto->getElementsByTagName('ICMS')->item(0) : null;
        $ipi = $imposto ? $imposto->getElementsByTagName('IPITrib')->item(0) : null;
        $is = $this->primeiroFilho($imposto, 'IS');
        $ibscbs = $this->primeiroFilho($imposto, 'IBSCBS');
        $gIbsCbs = $this->primeiroFilho($ibscbs, 'gIBSCBS');
        $gMono = $this->primeiroFilho($ibscbs, 'gIBSCBSMono');
        $baseIbsCbs = $this->textoNumero($gIbsCbs, 'vBC');

        $linhas = [];
        $icmsLinha = ['ICMS', $this->textoNumero($icms, 'vBC'), $this->textoPercentual($icms, 'pICMS'), $this->textoNumero($icms, 'vICMS')];
        if ($icmsLinha[1] . $icmsLinha[2] . $icmsLinha[3] !== '') {
            $linhas[] = $icmsLinha;
        }
        foreach ([['IBS UF', 'gIBSUF', 'pIBSUF', 'vIBSUF'], ['IBS MUN', 'gIBSMun', 'pIBSMun', 'vIBSMun'], ['CBS', 'gCBS', 'pCBS', 'vCBS']] as list($rotulo, $grupo, $p, $v)) {
            $g = $this->primeiroFilho($gIbsCbs, $grupo);
            if ($g) {
                $linhas[] = [$rotulo, $baseIbsCbs, $this->textoPercentual($g, $p), $this->textoNumero($g, $v)];
            }
        }
        // monofásico (UB84). A NT 2026.010 não lista campos do item para ele (só os
        // totais do gMono, §4.1); sem estas linhas a faixa de um combustível ficaria
        // sem tributo nenhum (gIBSCBS e gIBSCBSMono são excludentes). Só o que está
        // no XML; rótulos de até ~10,7 mm ("IBS MUN MON") para caberem na coluna TRIBUTO a 4,5 pt.
        $linhas = array_merge($linhas, $this->linhasMonofasico($gMono));
        if ($is) {
            $linhas[] = ['IS', $this->textoNumero($is, 'vBCIS'), $this->textoPercentual($is, 'pIS'), $this->textoNumero($is, 'vIS')];
        }
        if ($ipi) {
            $linhas[] = ['IPI', $this->textoNumero($ipi, 'vBC'), $this->textoPercentual($ipi, 'pIPI'), $this->textoNumero($ipi, 'vIPI')];
        }

        return $linhas;
    }

    /**
     * Linhas do IBS/CBS monofásico do item, [rótulo, base, alíquota, valor].
     *
     * Leiaute da NT 2025.002 v1.53 (UB85a–UB104b: gIBSMonoAdRem,
     * gIBSMonoAdValorem, gCBSMonoAdRem, gCBSMonoAdValorem, cada um com
     * gMonoPadrao/gMonoReten/gMonoRet) e o anterior (gMonoPadrao/gMonoReten/
     * gMonoRet direto no gIBSCBSMono, IBS e CBS juntos). O gpBioDiferenca não sai:
     * não está na NT 2026.010.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    protected function linhasMonofasico($gMono)
    {
        if (empty($gMono)) {
            return [];
        }
        $adRem = function ($g, $tag) {
            $v = $this->textoNumero($g, $tag, 4);

            return $v === '' ? '' : 'R$ ' . $v . '/un';
        };
        $linhas = [];
        foreach (['IBS', 'CBS'] as $t) {
            $grupo = $this->primeiroFilho($gMono, "g{$t}MonoAdRem");
            if ($g = $this->primeiroFilho($grupo, 'gMonoPadrao')) {
                $linhas[] = ["$t MONO", $this->textoQuantidade($g, 'qBCMono'), $adRem($g, "adRem$t"), $this->textoNumero($g, "v{$t}Mono")];
            }
            if ($g = $this->primeiroFilho($grupo, 'gMonoReten')) {
                $linhas[] = ["$t RETEN.", $this->textoQuantidade($g, 'qBCMonoReten'), $adRem($g, "adRem{$t}Reten"), $this->textoNumero($g, "v{$t}MonoReten")];
            }
            if ($g = $this->primeiroFilho($grupo, 'gMonoRet')) {
                $linhas[] = ["$t RET.ANT", '', '', $this->textoNumero($g, "v{$t}MonoRet")];
            }
            $grupo = $this->primeiroFilho($gMono, "g{$t}MonoAdValorem");
            if ($g = $this->primeiroFilho($grupo, 'gMonoPadrao')) {
                $base = $this->textoNumero($g, 'vBCMono');
                if ($t === 'IBS') {
                    $linhas[] = ['IBS UF MONO', $base, $this->textoPercentual($g, 'pAliqMonoUF'), $this->textoNumero($g, 'vIBSMonoUF')];
                    $linhas[] = ['IBS MUN MON', $base, $this->textoPercentual($g, 'pAliqMonoMun'), $this->textoNumero($g, 'vIBSMonoMun')];
                } else {
                    $linhas[] = ['CBS MONO', $base, $this->textoPercentual($g, 'pAliqMonoCBS'), $this->textoNumero($g, 'vCBSMono')];
                }
            }
            if ($g = $this->primeiroFilho($grupo, 'gMonoReten')) {
                $linhas[] = ["$t RETEN.", $this->textoNumero($g, 'vBCMonoReten'), $this->textoPercentual($g, 'pAliqMonoReten'), $this->textoNumero($g, "v{$t}MonoReten")];
            }
            if ($g = $this->primeiroFilho($grupo, 'gMonoRet')) {
                $linhas[] = ["$t RET.ANT", '', '', $this->textoNumero($g, "v{$t}MonoRet")];
            }
        }
        // leiaute anterior à v1.53
        foreach (['gMonoPadrao' => ['MONO', ''], 'gMonoReten' => ['RETEN.', 'Reten'], 'gMonoRet' => ['RET.ANT', 'Ret']] as $sub => list($rot, $suf)) {
            $g = $this->primeiroFilho($gMono, $sub);
            if (empty($g)) {
                continue;
            }
            $q = $this->textoQuantidade($g, 'qBCMono' . $suf);
            foreach (['IBS', 'CBS'] as $trib) {
                $linhas[] = ["$trib $rot", $q, $adRem($g, 'adRem' . $trib . $suf), $this->textoNumero($g, 'v' . $trib . 'Mono' . $suf)];
            }
        }

        return $linhas;
    }

    /**
     * Medida da descrição para o limite de altura do item (Patch 18 do Danfe):
     * coluna de descrição da faixa, fonte 6, linha de LH mm e +1 linha ([NCM] [cClassTrib]).
     */
    protected function medidaDescricao()
    {
        $cols = $this->colunasItem($this->larguraUtil(1));

        return [max(10, $cols[1] - 1) - 0.2, ['font' => $this->fontePadrao, 'size' => 6, 'style' => ''], self::LH, 1];
    }

    /**
     * Linha sob a descrição (NT §4.3): [NCM ...] [cClassTrib ...].
     */
    protected function rodapeItem($det)
    {
        $prod = $det->getElementsByTagName('prod')->item(0);
        $ncm = $prod ? $this->getTagValue($prod, 'NCM') : '';
        $classe = $this->getTagValue($this->primeiroFilho($det->getElementsByTagName('imposto')->item(0), 'IBSCBS'), 'cClassTrib');
        $partes = [];
        if ($ncm !== '') {
            $partes[] = '[NCM ' . $ncm . ']';
        }
        if ($classe !== '') {
            $partes[] = '[cClassTrib ' . $classe . ']';
        }

        return implode(' ', $partes);
    }

    /**
     * CST do ICMS com a origem, como no Clássico (orig + CST|CSOSN). Sem grupo
     * ICMS, o CST do IBS/CBS. (O CST do IBS/CBS também é o início do cClassTrib.)
     */
    protected function cstItem($det)
    {
        $imposto = $det->getElementsByTagName('imposto')->item(0);
        $icms = $imposto ? $imposto->getElementsByTagName('ICMS')->item(0) : null;
        if (!empty($icms)) {
            $cst = $this->getTagValue($icms, 'CST') . $this->getTagValue($icms, 'CSOSN');
            if ($cst !== '') {
                return $this->getTagValue($icms, 'orig') . $cst;
            }
        }

        return ''; // sem ICMS: em branco (o CST do IBS/CBS já é o início do [cClassTrib])
    }

    protected function textoPercentual($grupo, $tagCheia)
    {
        $numero = $this->textoAliquota($grupo, $tagCheia);

        return $numero === '' ? '' : $numero . '%';
    }

    /**
     * Com gRed (redução ou compra governamental), a alíquota impressa é a
     * efetiva pAliqEfet (UB28/UB47/UB66). De 2 a 4 casas, sem zero à direita.
     */
    protected function textoAliquota($grupo, $tagCheia)
    {
        if (empty($grupo)) {
            return '';
        }
        $gRed = $this->primeiroFilho($grupo, 'gRed');
        $origem = empty($gRed) ? $grupo : $gRed;
        $tag = empty($gRed) ? $tagCheia : 'pAliqEfet';
        $texto = $this->textoNumero($origem, $tag, 4);
        if ($texto === '') {
            return '';
        }
        // "0,1000" → "0,10"; "0,0125" fica; "12,3450" → "12,345"
        list($inteiro, $decimais) = explode(',', $texto);

        return $inteiro . ',' . str_pad(rtrim($decimais, '0'), 2, '0');
    }

    /**
     * Número no formato brasileiro; tag ausente → ''.
     */
    protected function textoNumero($node, $tag, $casas = 2)
    {
        if (empty($node)) {
            return '';
        }
        $campo = $this->primeiroFilho($node, $tag) ?: $node->getElementsByTagName($tag)->item(0);
        if (empty($campo) || trim($campo->nodeValue) === '') {
            return '';
        }

        return number_format((float) $campo->nodeValue, $casas, ',', '.');
    }

    /**
     * Quantidade com até 4 casas, sem zeros à direita.
     */
    protected function textoQuantidade($node, $tag)
    {
        $texto = $this->textoNumero($node, $tag, 4);

        return $texto === '' ? '' : rtrim(rtrim($texto, '0'), ',');
    }

    /**
     * @return \DOMElement|null
     */
    protected function primeiroFilho($node, $nome)
    {
        if (empty($node) || empty($node->childNodes)) {
            return null;
        }
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === $nome) {
                return $child;
            }
        }

        return null;
    }

    protected function rotuloCrt($crt)
    {
        $mapa = [
            '1' => '1 - SIMPLES NACIONAL',
            '2' => '2 - SIMPLES NACIONAL - EXCESSO DE SUBLIMITE DE RECEITA BRUTA',
            '3' => '3 - REGIME NORMAL',
            '4' => '4 - SIMPLES NACIONAL - MEI',
        ];

        return isset($mapa[$crt]) ? $mapa[$crt] : (string) $crt;
    }

    protected function larguraUtil($pag)
    {
        if ($this->orientacao === 'P' || (int) $pag !== 1) {
            return $this->wPrint;
        }

        return $this->wPrint - $this->wCanhoto;
    }
}
