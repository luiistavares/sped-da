<?php

namespace NFePHP\DA\CTe;

/**
 * Classe para ageração do PDF da CTe, conforme regras e estruturas
 * estabelecidas pela SEFAZ.
 *
 * @category  Library
 * @package   nfephp-org/sped-da
 * @name      Dacte .php
 * @copyright 2009-2016 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @link      http://github.com/nfephp-org/sped-da for the canonical source repository
 * @author    Roberto L. Machado <linux dot rlm at gmail dot com>
 */

use Com\Tecnick\Barcode\Barcode;
use Exception;
use NFePHP\DA\Legacy\Dom;
use NFePHP\DA\Legacy\Pdf;
use NFePHP\DA\Legacy\Common;

class DacteOSV3 extends Common
{
    use \NFePHP\DA\Common\XmlSanitizerTrait;
    use DacteLeiauteTrait; // PATCH 17

    const NFEPHP_SITUACAO_EXTERNA_CANCELADA = 1;
    const NFEPHP_SITUACAO_EXTERNA_DENEGADA = 2;
    const SIT_DPEC = 3;

    protected $logoAlign = 'C';
    protected $yDados = 0;
    protected $situacao_externa = 0;
    protected $numero_registro_dpec = '';
    protected $pdf;
    protected $xml;
    protected $logomarca = '';
    protected $errMsg = '';
    protected $errStatus = false;
    protected $orientacao = 'P';
    protected $papel = 'A4';
    protected $destino = 'I';
    protected $pdfDir = '';
    protected $fontePadrao = 'Times';
    protected $version = '1.3.0';
    protected $wPrint;
    protected $hPrint;
    protected $dom;
    protected $infCte;
    protected $infPercurso;
    protected $infCteComp;
    protected $chaveCTeRef;
    protected $tpCTe;
    protected $ide;
    protected $emit;
    protected $enderEmit;
    protected $infCarga;
    protected $infQ;
    protected $seg;
    protected $modal;
    protected $rodo;
    protected $moto;
    protected $veic;
    protected $ferrov;
    protected $Comp;
    protected $infNF;
    protected $infNFe;
    protected $compl;
    protected $ICMS;
    protected $imp;
    protected $toma4;
    protected $toma03;
    protected $tpEmis;
    protected $tpImp;
    protected $tpAmb;
    protected $vPrest;
    protected $infServico;
    protected $wAdic = 150;
    protected $textoAdic = '';
    protected $debugMode = 2;
    protected $formatPadrao;
    protected $formatNegrito;
    protected $aquav;
    protected $preVisualizar;
    protected $flagDocOrigContinuacao;
    protected $arrayNFe = array();
    protected $chavesCTeRef = array(); // PATCH 17 (e as propriedades abaixo, antes dinâmicas)
    protected $toma;
    protected $enderToma;
    protected $rem;
    protected $respSeg;
    protected $infOutros;
    protected $ICMSSN;
    protected $cteProc;
    protected $protCTe;
    protected $qrCodMDFe;
    protected $siteDesenvolvedor;
    protected $nomeDesenvolvedor;
    protected $totPag;
    protected $idDocAntEle = [];
    protected $lota;

    protected $formatoChave = "#### #### #### #### #### #### #### #### #### #### ####";
    protected $margemInterna = 0;

    /**
     * __construct
     *
     * @param string $docXML Arquivo XML da CTe
     * @param string $sOrientacao (Opcional) Orientação da impressão P ou L
     * @param string $sPapel Tamanho do papel (Ex. A4)
     * @param string $sPathLogo Caminho para o arquivo do logo
     * @param string $sDestino Estabelece a direção do envio do documento PDF
     * @param string $sDirPDF Caminho para o diretorio de armaz. dos PDF
     * @param string $fonteDACTE Nome da fonte a ser utilizada
     * @param number $mododebug 0-Não 1-Sim e 2-nada (2 default)
     * @param string $preVisualizar 0-Não 1-Sim
     */
    public function __construct(
        $docXML = '',
        $sOrientacao = '',
        $sPapel = '',
        $sPathLogo = '',
        $sDestino = 'I',
        $sDirPDF = '',
        $fonteDACTE = '',
        $mododebug = 2,
        $preVisualizar = false,
        $nomeDesenvolvedor = 'Powered by NFePHP (GNU/GPLv3 GNU/LGPLv3) © www.nfephp.org',
        $siteDesenvolvedor = 'http://www.nfephp.org'
    ) {

        if (is_numeric($mododebug)) {
            $this->debugMode = $mododebug;
        }
        if ($mododebug == 1) {
            //ativar modo debug
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
        } elseif ($mododebug == 0) {
            //desativar modo debug
            error_reporting(0);
            ini_set('display_errors', 'Off');
        }
        $this->orientacao = $sOrientacao;
        $this->papel = $sPapel;
        $this->pdf = '';
        $this->xml = $docXML;
        $this->logomarca = $sPathLogo;
        $this->destino = $sDestino;
        $this->pdfDir = $sDirPDF;
        $this->preVisualizar = $preVisualizar;
        $this->siteDesenvolvedor = $siteDesenvolvedor;
        $this->nomeDesenvolvedor = $nomeDesenvolvedor;
        // verifica se foi passa a fonte a ser usada
        if (!empty($fonteDACTE)) {
            $this->fontePadrao = $fonteDACTE;
        }
        $this->tituloDocs = 'DOCUMENTOS REFERENCIADOS'; // PATCH 17
        $this->formatPadrao = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->formatNegrito = array(
            'font' => $this->fontePadrao,
            'size' => 7,
            'style' => 'B');
        //se for passado o xml
        if (!empty($this->xml)) {
            // PATCH consultadanfe: blindagem 2-camadas contra XMLs mal-formados
            $this->xml = self::sanitizeXmlFreeText($this->xml);
            $this->dom = new Dom();
            $useInternalErrors = libxml_use_internal_errors(true);
            $loaded = $this->dom->loadXML($this->xml);
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
            if (!$loaded || !empty($errors)) {
                $this->xml = self::aggressiveEscape($this->xml);
                $this->dom = new Dom();
                $this->dom->loadXML($this->xml);
            }
            $this->cteProc = $this->dom->getElementsByTagName("cteOSProc")->item(0);
            $this->infCte = $this->dom->getElementsByTagName("infCte")->item(0);
            $this->ide = $this->dom->getElementsByTagName("ide")->item(0);
            $this->emit = $this->dom->getElementsByTagName("emit")->item(0);
            $this->enderEmit = $this->dom->getElementsByTagName("enderEmit")->item(0);

            $this->infPercurso = $this->dom->getElementsByTagName("infPercurso");
            $this->infCarga = $this->dom->getElementsByTagName("infCarga")->item(0);
            $this->infQ = $this->dom->getElementsByTagName("infQ");
            $this->seg = $this->dom->getElementsByTagName("seg")->item(0);
            $this->rodo = $this->dom->getElementsByTagName("rodoOS")->item(0);


            $this->veic = $this->dom->getElementsByTagName("veic");
            $this->ferrov = $this->dom->getElementsByTagName("ferrov")->item(0);
            // adicionar outros modais
            $this->infCteComp = $this->dom->getElementsByTagName("infCteComp")->item(0);
            $this->chaveCTeRef = $this->getTagValue($this->infCteComp, "chave");
            // PATCH 17: no 3.00/4.00 a chave do CT-e complementado é "chCTe"
            foreach ($this->dom->getElementsByTagName("infCteComp") as $comp) {
                $ch = $this->getTagValue($comp, "chCTe") ?: $this->getTagValue($comp, "chave");
                if ($ch !== '') {
                    $this->chavesCTeRef[] = $ch;
                }
            }
            if ($this->chaveCTeRef === '' && $this->chavesCTeRef) {
                $this->chaveCTeRef = $this->chavesCTeRef[0];
            }
            $this->vPrest = $this->dom->getElementsByTagName("vPrest")->item(0);
            $this->Comp = $this->dom->getElementsByTagName("Comp");
            $this->infNF = $this->dom->getElementsByTagName("infNF");
            $this->infNFe = $this->dom->getElementsByTagName("infNFe");
            $this->infOutros = $this->dom->getElementsByTagName("infOutros");
            $this->infServico = $this->dom->getElementsByTagName("infServico");
            $this->compl = $this->dom->getElementsByTagName("compl");
            $this->ICMS = $this->dom->getElementsByTagName("ICMS")->item(0);
            $this->ICMSSN = $this->dom->getElementsByTagName("ICMSSN")->item(0);
            $this->imp = $this->dom->getElementsByTagName("imp")->item(0);

            $vTrib = $this->getTagValue($this->imp, "vTotTrib");
            if (!is_numeric($vTrib)) {
                $vTrib = 0;
            }
            $textoAdic = number_format((float)$vTrib, 2, ",", ".");

            $this->textoAdic = "o valor aproximado de tributos incidentes sobre o preço deste serviço é de R$"
                    .$textoAdic;
            $this->toma = $this->dom->getElementsByTagName("toma")->item(0);
            $this->enderToma = $this->toma !== null ? $this->toma->getElementsByTagName("enderToma")->item(0) : null; // PATCH 17
            //modal aquaviário
            $this->aquav = $this->dom->getElementsByTagName("aquav")->item(0);

            $seguro = $this->getTagValue($this->seg, "respSeg");
            switch ($seguro) {
                case '4':
                    $this->respSeg = 'Emitente';
                    break;
                case '5':
                    $this->respSeg = 'Tomador do Serviço';
                    break;
                default:
                    $this->respSeg = '';
                    break;
            }
            $this->tpEmis = $this->getTagValue($this->ide, "tpEmis");
            $this->tpImp = $this->getTagValue($this->ide, "tpImp");
            $this->tpAmb = $this->getTagValue($this->ide, "tpAmb");
            $this->tpCTe = $this->getTagValue($this->ide, "tpCTe");
            $this->protCTe = $this->dom->getElementsByTagName("protCTe")->item(0);
            $this->qrCodMDFe = $this->dom->getElementsByTagName('qrCodCTe')->item(0) ?
                $this->dom->getElementsByTagName('qrCodCTe')->item(0)->nodeValue : null;
            //01-Rodoviário; //02-Aéreo; //03-Aquaviário; //04-Ferroviário;//05-Dutoviário
            $this->modal = $this->getTagValue($this->ide, "modal");
        }
    }

    /**
     * monta
     * @param string $orientacao L ou P
     * @param string $papel A4
     * @param string $logoAlign C, L ou R
     * @param Pdf $classPDF
     * @return string montagem
     */
    public function monta(
        $orientacao = '',
        $papel = 'A4',
        $logoAlign = 'C',
        $classPDF = false
    ) {

        return $this->montaDACTE($orientacao, $papel, $logoAlign, $classPDF);
    }

    /**
     * printDocument
     * @param string $nome
     * @param string $destino
     * @param string $printer
     * @return
     */
    public function printDocument($nome = '', $destino = 'I', $printer = '')
    {
        return $this->printDACTE($nome, $destino, $printer);
    }

    /**
     * Dados brutos do PDF
     * @return string
     */
    public function render()
    {
        return $this->pdf->getPdf();
    }


    protected function zCteDPEC()
    {
        return $this->situacao_externa == self::SIT_DPEC && $this->numero_registro_dpec != '';
    }


    /**
     * montaDACTE
     * Esta função monta a DACTE conforme as informações fornecidas para a classe
     * durante sua construção.
     * A definição de margens e posições iniciais para a impressão são estabelecidas no
     * pelo conteúdo da funçao e podem ser modificados.
     *
     * @param  string $orientacao (Opcional) Estabelece a orientação da
     *                impressão (ex. P-retrato), se nada for fornecido será
     *                usado o padrão da NFe
     * @param  string $papel (Opcional) Estabelece o tamanho do papel (ex. A4)
     * @return string O ID da NFe numero de 44 digitos extraido do arquivo XML
     */
    public function montaDACTE(
        $orientacao = '',
        $papel = 'A4',
        $logoAlign = 'C',
        $classPDF = false
    ) {

        //se a orientação estiver em branco utilizar o padrão estabelecido na NF
        if ($orientacao == '') {
            if ($this->tpImp == '1') {
                $orientacao = 'P';
            } else {
                $orientacao = 'P';
            }
        }
        $this->orientacao = $orientacao;
        $this->papel = $papel;
        $this->logoAlign = $logoAlign;
        // PATCH 17: duas passadas (ver Dacte::montaDACTE)
        if (!$this->planejando) {
            $this->planejando = true;
            $this->montaDACTE($orientacao, $papel, $logoAlign, new Pdf($orientacao === 'L' ? 'L' : 'P', 'mm', $papel));
            $this->totalFolhas = 1 + $this->zContinuacao(false, $this->zColunasDocs(), $this->zColunasComp());
            $this->excedentes = [];
            $this->docsPendentes = [];
            $this->compPendentes = [];
            $this->textosPendentes = [];
            $this->desenhando = true;
            $r = $this->montaDACTE($orientacao, $papel, $logoAlign, $classPDF);
            $this->planejando = false;
            $this->desenhando = false;
            return $r;
        }

        //$this->situacao_externa = $situacao_externa;
        //instancia a classe pdf
        if ($classPDF !== false) {
            $this->pdf = $classPDF;
        } else {
            $this->pdf = new Pdf($this->orientacao, 'mm', $this->papel);
        }
        if ($this->orientacao == 'P') {
            // margens do PDF
            $margSup = 2;
            $margEsq = 2;
            $margDir = 2;
            // posição inicial do relatorio
            $xInic = 1;
            $yInic = 1;
            if ($papel == 'A4') {
                //A4 210x297mm
                $maxW = 210;
                $maxH = 297;
            }
        } else {
            // margens do PDF
            $margSup = 3;
            $margEsq = 3;
            $margDir = 3;
            // posição inicial do relatorio
            $xInic = 5;
            $yInic = 5;
            if ($papel == 'A4') {
                //A4 210x297mm
                $maxH = 210;
                $maxW = 297;
                $this->wCanhoto = 25;
            }
        }
        //total inicial de paginas
        $totPag = 1;
        //largura imprimivel em mm
        $this->wPrint = $maxW - ($margEsq + $xInic);
        //comprimento imprimivel em mm
        $this->hPrint = $maxH - ($margSup + $yInic);
        // estabelece contagem de paginas
        $this->pdf->AliasNbPages();
        // fixa as margens
        $this->pdf->SetMargins($margEsq, $margSup, $margDir);
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetFillColor(255, 255, 255);
        // inicia o documento
        $this->pdf->Open();
        // adiciona a primeira página
        $this->pdf->AddPage($this->orientacao, $this->papel);
        $this->pdf->SetLineWidth(0.1);
        $this->pdf->SetTextColor(0, 0, 0);
        //calculo do numero de páginas ???
        $totPag = 1;
        //montagem da primeira página
        $pag = 1;
        $x = $xInic;
        $y = $yInic;
        //coloca o cabeçalho
        //$r = $this->zCabecalho($x, $y, $pag, $totPag);
        $y += 70;
        $r = $this->zTomador($x, $y);
        // PATCH 17: CT-e OS substituto (tpCTe 3) tem infCTeNorm → leiaute normal
        if ($this->tpCTe == '0' || $this->dom->getElementsByTagName('infCTeNorm')->length > 0) {
            //Normal
            $y += 10;
            $x = $xInic;
            //$r = $this->zDocOrig($x, $y);
            $r = $this->zInfPrestacaoServico($x, $y);
            $y += 53;
            $x = $xInic;
            $r = $this->zCompValorServ($x, $y);
            $y += 25;
            $x = $xInic;
            $r = $this->zImpostos($x, $y);
            $y += 13;
            $x = $xInic;
            $r = $this->zObs($x, $y);
            $y += 19;
            $x = $xInic;
            $r = $this->zSeguro($x, $y);
            $y = $y-12;


            switch ($this->modal) {
                case '1':
                    $y += 24.9;
                    $x = $xInic;
                    $r = $this->zModalRod($x, $y);
                    break;
                case '2':
                    $y += 24.9; // PATCH 17: com 17,9 o quadro do modal cobria o SEGURO DA VIAGEM
                    $x = $xInic;
                    // TODO fmertins 31/10/14: este método não existe...
                    $r = $this->zModalAereo($x, $y);
                    break;
                case '3':
                    $y += 24.9; // PATCH 17
                    $x = $xInic;
                    $r = $this->zModalAquaviario($x, $y);
                    break;
                case '4':
                    $y += 24.9; // PATCH 17
                    $x = $xInic;
                    $r = $this->zModalFerr($x, $y);
                    break;
                case '5':
                    $y += 24.9; // PATCH 17
                    $x = $xInic;
                    // TODO fmertins 31/10/14: este método não existe...
                    $r = $this->zModalDutoviario($x, $y);
                    break;
            }
            if ($this->modal == '1') {
                if ($this->lota == 1) {
                    $y += 37;
                } else {
                    $y += 8.9;
                }
            } elseif ($this->modal == '3') {
                $y += 24.15;
            } else {
                $y += 37;
            }
        } else {
            $r = $this->zCabecalho(1, 1, '1', (string) $this->totalFolhas);
            //Complementado
            $y += 10;
            $x = $xInic;
            $r = $this->zDocCompl($x, $y);
            $y += 80;
            $x = $xInic;
            $r = $this->zCompValorServ($x, $y);
            $y += 25;
            $x = $xInic;
            $r = $this->zImpostos($x, $y);
            $y += 13;
            $x = $xInic;
            $r = $this->zObs($x, $y);
            $y += 15;
        }
        $x = $xInic;
        $y += 1;
        $r = $this->zDadosAdic($x, $y, $pag, $totPag);

        $y += 21;
        //$y += 11;
        $y = $this->zCanhoto($x, $y);

        //coloca o rodapé da página
        if ($this->orientacao == 'P') {
            $this->zRodape(2, $this->hPrint - 2);
        } else {
            $this->zRodape($xInic, $this->hPrint + 2.3);
        }
        // PATCH 17: folhas de continuação
        if ($this->desenhando) {
            $this->zContinuacao(true, $this->zColunasDocs(), $this->zColunasComp());
        }
        //retorna o ID na CTe
        if ($classPDF !== false) {
            $aR = array('id' => str_replace('CTe', '', $this->infCte->getAttribute("Id")), 'classe_PDF' => $this->pdf);
            return $aR;
        } else {
            return str_replace('CTe', '', $this->infCte->getAttribute("Id"));
        }
    } //fim da função montaDACTE

    /**
     * printDACTE
     * Esta função envia a DACTE em PDF criada para o dispositivo informado.
     * O destino da impressão pode ser :
     * I-browser
     * D-browser com download
     * F-salva em um arquivo local com o nome informado
     * S-retorna o documento como uma string e o nome é ignorado.
     * Para enviar o pdf diretamente para uma impressora indique o
     * nome da impressora e o destino deve ser 'S'.
     *
     * @param  string $nome Path completo com o nome do arquivo pdf
     * @param  string $destino Direção do envio do PDF
     * @param  string $printer Identificação da impressora no sistema
     * @return string Caso o destino seja S o pdf é retornado como uma string
     * @todo Rotina de impressão direta do arquivo pdf criado
     */
    public function printDACTE($nome = '', $destino = 'I', $printer = '')
    {
        $arq = $this->pdf->Output($nome, $destino);
        if ($destino == 'S') {
            //aqui pode entrar a rotina de impressão direta
        }
        return $arq;
    } //fim função printDACTE

    /**
     * zCabecalho
     * Monta o cabelhalho da DACTE ( retrato e paisagem )
     *
     * @param  number $x Posição horizontal inicial, canto esquerdo
     * @param  number $y Posição vertical inicial, canto superior
     * @param  number $pag Número da Página
     * @param  number $totPag Total de páginas
     * @return number Posição vertical final
     */
    protected function zCabecalho($x = 0, $y = 0, $pag = '1', $totPag = '1')
    {
        $oldX = $x;
        $oldY = $y;
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            if ($pag == 1) {
                // primeira página
                $maxW = $this->wPrint - $this->wCanhoto;
            } else {
                // páginas seguintes
                $maxW = $this->wPrint;
            }
        }
        //##################################################################
        //coluna esquerda identificação do emitente
        $w = round($maxW * 0.42);
        if ($this->orientacao == 'P') {
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 6,
                'style' => '');
        } else {
            $aFont = $this->formatNegrito;
        }
        $w1 = $w;
        $h = 35;
        $oldY += $h;
        //desenha a caixa
        $this->pTextBox($x, $y, $w + 2, $h + 1);
        // coloca o logo
        if (is_file($this->logomarca)) {
            $logoInfo = getimagesize($this->logomarca);
            //largura da imagem em mm
            $logoWmm = ($logoInfo[0] / 72) * 25.4;
            //altura da imagem em mm
            $logoHmm = ($logoInfo[1] / 72) * 25.4;
            if ($this->logoAlign == 'L') {
                $nImgW = round($w / 3, 0);
                $nImgH = round($logoHmm * ($nImgW / $logoWmm), 0);
                $xImg = $x + 1;
                $yImg = round(($h - $nImgH) / 2, 0) + $y;
                //estabelecer posições do texto
                $x1 = round($xImg + $nImgW + 1, 0);
                $y1 = round($h / 3 + $y, 0);
                $tw = round(2 * $w / 3, 0);
            } elseif ($this->logoAlign == 'C') {
                $nImgH = round($h / 3, 0);
                $nImgW = round($logoWmm * ($nImgH / $logoHmm), 0);
                $xImg = round(($w - $nImgW) / 2 + $x, 0);
                $yImg = $y + 3;
                $x1 = $x;
                $y1 = round($yImg + $nImgH + 1, 0);
                $tw = $w;
            } elseif ($this->logoAlign == 'R') {
                $nImgW = round($w / 3, 0);
                $nImgH = round($logoHmm * ($nImgW / $logoWmm), 0);
                $xImg = round($x + ($w - (1 + $nImgW)), 0);
                $yImg = round(($h - $nImgH) / 2, 0) + $y;
                $x1 = $x;
                $y1 = round($h / 3 + $y, 0);
                $tw = round(2 * $w / 3, 0);
            }
            $this->pdf->Image($this->logomarca, $xImg, $yImg, $nImgW, $nImgH, 'jpeg');
        } else {
            $x1 = $x;
            $y1 = round($h / 3 + $y, 0);
            $tw = $w;
        }
        //Nome emitente
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 9,
            'style' => 'B');
        $texto = $this->getTagValue($this->emit, "xNome");
        // PATCH 17: nome e endereço quebram dentro do quadro (nada de microtexto
        // nem de texto passando por cima do CNPJ); o complemento (xCpl) do
        // emitente era lido e descartado — agora sai, como pede o Anexo II §3.
        $this->zCampo($x1, $y1, $tw, 4.6, $texto, $aFont, 'EMITENTE - NOME', 'C');
        //endereço
        $y1 = $y1 + 3;
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 7,
            'style' => '');
        $fone = $this->zFormatFone($this->enderEmit);
        $CEP = $this->getTagValue($this->enderEmit, "CEP");
        $CEP = $this->pFormat($CEP, "#####-###");
        $mun = $this->getTagValue($this->enderEmit, "xMun");
        $UF = $this->getTagValue($this->enderEmit, "UF");
        $xPais = $this->getTagValue($this->enderEmit, "xPais");
        $texto = $this->zEnderecoTexto($this->enderEmit) . "\n"
            . $CEP . " - " . $mun . " - " . $UF . " " . $xPais
            . "\nFone/Fax: " . $fone;
        $this->zCampo($x1, $y1 + 2, $tw, 9.6, $texto, $aFont, 'EMITENTE - ENDEREÇO', 'C');
        //CNPJ/CPF IE
        $cpfCnpj = $this->zFormatCNPJCPF($this->emit);
        $ie = $this->getTagValue($this->emit, "IE");
        $texto = 'CNPJ/CPF:  ' . $cpfCnpj . '     Insc.Estadual: ' . $ie;
        $this->zCaixaTexto($x1, $y1 + 12, $tw, 3, $texto, $aFont, 'C');
        //outra caixa
        $h1 = 17.5;
        $y1 = $y + $h + 1;
        $this->pTextBox($x, $y1, $w + 2, $h1);
        //TIPO DO CT-E
        $texto = 'TIPO DO CTE';
        $wa = 37;
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($x, $y1, $w * 0.5, $h1, $texto, $aFont, 'T', 'C', 0, '');
        $tpCTe = $this->getTagValue($this->ide, "tpCTe");
        //0 - CT-e Normal,1 - CT-e de Complemento de Valores,
        //2 - CT-e de Anulação de Valores,3 - CT-e Substituto
        switch ($tpCTe) {
            case '0':
                $texto = 'Normal';
                break;
            case '1':
                $texto = 'Complemento de Valores';
                break;
            case '2':
                $texto = 'Anulação de Valores';
                break;
            case '3':
                $texto = 'Substituto';
                break;
            default:
                $texto = $tpCTe; // PATCH 17: valor do XML, sem "ERRO"
        }
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y1 + 3, $w * 0.5, $h1, $texto, $aFont, 'T', 'C', 0, '', false);
        //TIPO DO SERVIÇO
        $texto = 'TIPO DO SERVIÇO';
        $wb = 36;
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($x + $wa + 4.5, $y1, $w * 0.5, $h1, $texto, $aFont, 'T', 'C', 0, '');
        $tpServ = $this->getTagValue($this->ide, "tpServ");
        //'6' => 'Transporte de Pessoas', '7' => 'Transporte de Valores', '8' => 'Transporte de Bagagem'
        switch ($tpServ) {
            case '6':
                $texto = 'Transporte de Pessoas';
                break;
            case '7':
                $texto = 'Transporte de Valores';
                break;
            case '8':
                $texto = 'Transporte de Bagagem';
                break;
            default:
                $texto = $tpServ; // PATCH 17
        }
        $aFont = $this->formatNegrito;
        $this->pTextBox($x + $wa + 4.5, $y1 + 3, $w * 0.5, $h1, $texto, $aFont, 'T', 'C', 0, '', false);
        $this->pdf->Line($w * 0.5, $y1, $w * 0.5, $y1 + $h1);
        //TOMADOR DO SERVIÇO
        $aFont = $this->formatNegrito;

        //##################################################################
        //coluna direita
        $x += $w + 2;
        $w = round($maxW * 0.335);
        $w1 = $w;
        $h = 11;
        $this->pTextBox($x, $y, $w + 21, $h + 1); // PATCH 17: coluna de 90 mm
        $texto = "DACTE OS";
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 10,
            'style' => 'B');
        $this->pTextBox($x, $y + 1, $w + 21, $h, $texto, $aFont, 'T', 'C', 0, '');
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 9,
            'style' => '');
        $texto = "Documento Auxiliar do Conhecimento\nde Transporte Eletrônico para Outros Serviços";
        $h = 10;
        $this->pTextBox($x, $y + 4, $w + 21, $h, $texto, $aFont, 'T', 'C', 0, '', false);
        $x1 = $x + $w + 2;
        $w = round($maxW * 0.22, 0);
        $w2 = $w;
        $h = 11;
        $this->pTextBox($x1 + 19, $y, $w - 18.5, $h + 1);
        $texto = "MODAL";
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 10,
            'style' => '');
        $this->pTextBox($x1 + 19, $y + 1, $w - 18.5, $h, $texto, $aFont, 'T', 'C', 0, '');
        switch ($this->modal) {
            case '1':
                $texto = 'Rodoviário';
                break;
            case '2':
                $texto = 'Aéreo';
                break;
            case '3':
                $texto = 'Aquaviário';
                break;
            case '4':
                $texto = 'Ferroviário';
                break;
            case '5':
                $texto = 'Dutoviário';
                break;
        }
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 10,
            'style' => 'B');
        $this->zCaixaTexto($x1 + 19, $y + 5, $w - 18.5, 5, $texto, $aFont, 'C');
        //outra caixa
        $y += 12;
        $h = 9;
        $w = $w1 + 21;
        $this->pTextBox($x, $y, $w, $h + 1);
        //modelo
        $wa = 12;
        $xa = $x;
        $texto = 'MODELO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($xa, $y + 1, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $texto = $this->getTagValue($this->ide, "mod");
        $aFont = $this->formatNegrito;
        $this->pTextBox($xa, $y + 5, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $this->pdf->Line($x + $wa, $y, $x + $wa, $y + $h + 1);
        //serie
        $xa += $wa;
        $texto = 'SÉRIE';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($xa, $y + 1, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $texto = $this->getTagValue($this->ide, "serie");
        $aFont = $this->formatNegrito;
        $this->pTextBox($xa, $y + 5, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $this->pdf->Line($xa + $wa, $y, $xa + $wa, $y + $h + 1);
        //numero
        $xa += $wa;
        $wa = 20;
        $texto = 'NÚMERO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($xa, $y + 1, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $texto = $this->getTagValue($this->ide, "nCT");
        $aFont = $this->formatNegrito;
        $this->pTextBox($xa, $y + 5, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $this->pdf->Line($xa + $wa, $y, $xa + $wa, $y + $h + 1);
        //folha (PATCH 17: o DACTE OS não tinha FL — Anexo II §2.21.2)
        $xa += $wa;
        $wa = 11;
        $this->pTextBox($xa, $y + 1, $wa, $h, 'FL', array('font' => $this->fontePadrao, 'size' => 8, 'style' => ''), 'T', 'C', 0, '');
        $this->pTextBox($xa, $y + 5, $wa, $h, $pag . '/' . $totPag, $this->formatNegrito, 'T', 'C', 0, '');
        $this->pdf->Line($xa + $wa, $y, $xa + $wa, $y + $h + 1);
        //data  hora de emissão
        $xa += $wa;
        $wa = 35;
        $texto = 'DATA E HORA DE EMISSÃO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($xa, $y + 1, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $this->pdf->Line($xa + $wa, $y, $xa + $wa, $y + $h + 1);
        $texto = !empty($this->ide->getElementsByTagName("dhEmi")->item(0)->nodeValue) ?
            date('d/m/Y H:i:s', $this->pConvertTime($this->getTagValue($this->ide, "dhEmi"))) : '';
        $aFont = $this->formatNegrito;
        $this->pTextBox($xa, $y + 5, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $this->pdf->Line($xa + $wa, $y, $xa + $wa, $y + $h + 1);
        //outra caixa
        $y += $h + 1;
        $h = 23;
        $h1 = 14;
        //$this->pTextBox($x, $y, $w + 0.5, $h1);
        //CODIGO DE BARRAS
        $chave_acesso = str_replace('CTe', '', $this->infCte->getAttribute("Id"));
        // PATCH 17 (SITE-11): 85 mm centralizado como no Dacte (era 75 mm, módulo de
        // 0,225 mm, lido só em 4x); margem clara de 2,5 mm de cada lado
        $bW = 85;
        $bH = 10;
        //codigo de barras
        $this->pdf->SetFillColor(0, 0, 0);
        $this->pdf->Code128($x + ($w - $bW) / 2, $y + 2, $chave_acesso, $bW, $bH);
        $this->pdf->Line($xa + $wa, $y, $xa + $wa, $y + $h - 1);
        $this->pTextBox($x, $y + $h1, $w + 0.5, $h1 - 6);
        $texto = 'CHAVE DE ACESSO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x + 1, $y + $h1, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $aFont = $this->formatNegrito;
        $texto = $this->pFormat($chave_acesso, $this->formatoChave);
        $this->pTextBox($x + 1, $y + $h1 + 3, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $this->pTextBox($x, $y + $h1 + 8, $w + 0.5, $h1 - 4.5);
        $texto = "Consulta de autenticidade no portal nacional do CT-e, ";
        $texto .= "no site da Sefaz Autorizadora, \r\n ou em http://www.cte.fazenda.gov.br";
        if ($this->tpEmis == 5 || $this->tpEmis == 7 || $this->tpEmis == 8) {
            $texto = "";
            $this->pdf->SetFillColor(0, 0, 0);
            if ($this->tpEmis == 5) {
                $chaveContingencia = $this->zGeraChaveAdicCont();
                $this->pdf->Code128($x + 20, $y1 + 10, $chaveContingencia, $bW * .9, $bH / 2);
            } else {
                $chaveContingencia = $this->getTagValue($this->protCTe, "nProt");
                $this->pdf->Code128($x + 40, $y1 + 10, $chaveContingencia, $bW * .4, $bH / 2);
            }
            //codigo de barras
        }
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($x, $y + $h1 + 9, $w, $h, $texto, $aFont, 'T', 'C', 0, '');
        //outra caixa
        $y += $h + 1;
        $h = 8.5;
        $wa = $w;
        $this->pTextBox($x, $y + 7.5, $w + 0.5, $h);
        if ($this->zCteDPEC()) {
            $texto = 'NÚMERO DE REGISTRO DPEC';
        } elseif ($this->tpEmis == 5 || $this->tpEmis == 7 || $this->tpEmis == 8) {
            $texto = "DADOS DO CT-E";
        } else {
            $texto = 'PROTOCOLO DE AUTORIZAÇÃO DE USO';
        }
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y + 7.5, $wa, $h, $texto, $aFont, 'T', 'L', 0, '');
        if ($this->zCteDPEC()) {
            $texto = $this->numero_registro_dpec;
        } elseif ($this->tpEmis == 5) {
            $chaveContingencia = $this->zGeraChaveAdicCont();
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 8,
                'style' => 'B');
            $texto = $this->pFormat($chaveContingencia, "#### #### #### #### #### #### #### #### ####");
            $cStat = '';
        } else {
            $texto = $this->getTagValue($this->protCTe, "nProt") . " - ";
            // empty($volume->getElementsByTagName("qVol")->item(0)->nodeValue)
            if (!empty($this->protCTe)
                && !empty($this->protCTe->getElementsByTagName("dhRecbto")->item(0)->nodeValue)
            ) {
                $texto .= date(
                    'd/m/Y   H:i:s',
                    $this->pConvertTime($this->getTagValue($this->protCTe, "dhRecbto"))
                );
            }
            $texto = $this->getTagValue($this->protCTe, "nProt") == '' ? '' : $texto;
        }
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y + 12, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');

        if ($this->qrCodMDFe !== null) {
            $this->pQRDACTEOS($y-25);
            $w = 27.5; // PATCH 17: coluna do QR Code depois da coluna de 90 mm
            $x += 90;
            $this->pTextBox($x, $y - 34, $w + 0.5, $h + 41.5);
        }
        //CFOP
        $x = $oldX;
        $h = 8.5;
        $w = round($maxW * 0.42);
        $y1 = $y + 7.5;
        $this->pTextBox($x, $y1, $w + 2, $h);
        $texto = 'CFOP - NATUREZA DA PRESTAÇÃO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($x, $y1, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ide, "CFOP") . ' - ' . $this->getTagValue($this->ide, "natOp");
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y1 + 3.5, $w, 4.6, $texto, $aFont, 'CFOP - NATUREZA DA PRESTAÇÃO'); // PATCH 17
        //ORIGEM DA PRESTAÇÃO
        $y += $h + 7.5;
        $x = $oldX;
        $h = 8;
        $w = ($maxW * 0.33);
        $this->pTextBox($x, $y, $w + 0.5, $h);
        $texto = 'INÍCIO DA PRESTAÇÃO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ide, "xMunIni") . ' - ' . $this->getTagValue($this->ide, "UFIni");
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3.5, $w, 4.3, $texto, $aFont, 'INÍCIO DA PRESTAÇÃO'); // PATCH 17
        //PERCURSO DO VEÍCULO
        $x = $oldX + 69;
        $oldX = $x;
        $h = 8;
        $w = ($maxW * 0.334);
        $this->pTextBox($x, $y, $w + 0.5, $h);
        $texto = 'PERCURSO DO VEÍCULO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = '';
        $first = true;
        if (!empty($this->infPercurso)) {
            foreach ($this->infPercurso as $k => $d) {
                if (!$first) {
                    $texto .= ' - ';
                } else {
                    $first = false;
                }
                $texto .= $this->infPercurso->item($k)->getElementsByTagName('UFPer')->item(0)->nodeValue;
            }
        }
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3.5, $w, 4.3, $texto, $aFont, 'PERCURSO DO VEÍCULO'); // PATCH 17
        //DESTINO DA PRESTAÇÃO
        $x = $oldX + $w + 1;
        $h = 8;
        $w = $w - 1.3;
        $this->pTextBox($x - 0.5, $y, $w + 0.5, $h);
        $texto = 'TÉRMINO DA PRESTAÇÃO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ide, "xMunFim") . ' - ' . $this->getTagValue($this->ide, "UFFim");
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3.5, $w, 4.3, $texto, $aFont, 'TÉRMINO DA PRESTAÇÃO'); // PATCH 17
        //#########################################################################
        //Indicação de CTe Homologação, cancelamento e falta de protocolo
        $tpAmb = $this->ide->getElementsByTagName('tpAmb')->item(0)->nodeValue;
        //indicar cancelamento
        $cStat = $this->getTagValue($this->cteProc, "cStat");
        if ($cStat == '101' || $cStat == '135' || $this->situacao_externa == self::NFEPHP_SITUACAO_EXTERNA_CANCELADA) {
            //101 Cancelamento
            $x = 10;
            $y = $this->hPrint - 130;
            $h = 25;
            $w = $maxW - (2 * $x);
            $this->pdf->SetTextColor(90, 90, 90);
            $texto = "CTe CANCELADO";
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 48,
                'style' => 'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(0, 0, 0);
        }
        $cStat = $this->getTagValue($this->cteProc, "cStat");
        if ($cStat == '110' ||
            $cStat == '301' ||
            $cStat == '302' ||
            $this->situacao_externa == self::NFEPHP_SITUACAO_EXTERNA_DENEGADA
        ) {
            //110 Denegada
            $x = 10;
            $y = $this->hPrint - 130;
            $h = 25;
            $w = $maxW - (2 * $x);
            $this->pdf->SetTextColor(90, 90, 90);
            $texto = "CTe USO DENEGADO";
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 48,
                'style' => 'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $y += $h;
            $h = 5;
            $w = $maxW - (2 * $x);
            $texto = "SEM VALOR FISCAL";
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 48,
                'style' => 'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(0, 0, 0);
        }
        //indicar sem valor
        if ($tpAmb != 1 && $this->preVisualizar=='0') { // caso não seja uma DA de produção
            $x = 10;
            if ($this->orientacao == 'P') {
                $y = round($this->hPrint / 2, 0);
            } else {
                $y = round($this->hPrint / 2, 0);
            }
            $h = 5;
            $w = $maxW - (2 * $x);
            $this->pdf->SetTextColor(90, 90, 90);
            $texto = "SEM VALOR FISCAL";
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 48,
                'style' => 'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 30,
                'style' => 'B');
            $texto = "AMBIENTE DE HOMOLOGAÇÃO";
            $this->pTextBox($x, $y + 14, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(0, 0, 0);
        } elseif ($this->preVisualizar=='1') { // caso seja uma DA de Pré-Visualização
            $h = 5;
            $w = $maxW - (2 * 10);
            $x = 55;
            $y = 240;
            $this->pdf->SetTextColor(255, 100, 100);
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 40,
                'style' => 'B');
            $texto = "Pré-visualização";
            $this->pTextBox90($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(255, 100, 100);
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 41,
                'style' => 'B');
            $texto = "Sem Validade Jurídica";
            $this->pTextBox90($x+20, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(90, 90, 90);
            $texto = "SEM VALOR FISCAL";
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 48,
                'style' => 'B');
            $this->pTextBox90($x+40, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(0, 0, 0); // voltar a cor default
        } else {
            $x = 10;
            if ($this->orientacao == 'P') {
                $y = round($this->hPrint * 2 / 3, 0);
            } else {
                $y = round($this->hPrint / 2, 0);
            } //fim orientacao
            $h = 5;
            $w = $maxW - (2 * $x);
            $this->pdf->SetTextColor(90, 90, 90);
            //indicar FALTA DO PROTOCOLO se NFe não for em contingência
            if (($this->tpEmis == 5 || $this->tpEmis == 7 || $this->tpEmis == 8) && !$this->zCteDPEC()) {
                //Contingência
                $texto = "DACTE Emitido em Contingência";
                $aFont = array(
                    'font' => $this->fontePadrao,
                    'size' => 48,
                    'style' => 'B');
                $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                $aFont = array(
                    'font' => $this->fontePadrao,
                    'size' => 30,
                    'style' => 'B');
                $texto = "devido à problemas técnicos";
                $this->pTextBox($x, $y + 12, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            } else {
                if (!isset($this->protCTe)) {
                    if (!$this->zCteDPEC()) {
                        $texto = "SEM VALOR FISCAL";
                        $aFont = array(
                            'font' => $this->fontePadrao,
                            'size' => 48,
                            'style' => 'B');
                        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                    }
                    $aFont = array(
                        'font' => $this->fontePadrao,
                        'size' => 30,
                        'style' => 'B');
                    $texto = "FALTA PROTOCOLO DE APROVAÇÃO DA SEFAZ";
                    if (!$this->zCteDPEC()) {
                        $this->pTextBox($x, $y + 12, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                    } else {
                        $this->pTextBox($x, $y + 25, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                    }
                } //fim cteProc
                if ($this->tpEmis == 4) {
                    //DPEC
                    $x = 10;
                    $y = $this->hPrint - 130;
                    $h = 25;
                    $w = $maxW - (2 * $x);
                    $this->pdf->SetTextColor(200, 200, 200); // 90,90,90 é muito escuro
                    $texto = "DACTE impresso em contingência -\n"
                        . "DPEC regularmente recebido pela Receita\n"
                        . "Federal do Brasil";
                    $aFont = array(
                        'font' => $this->fontePadrao,
                        'size' => 48,
                        'style' => 'B');
                    $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                    $this->pdf->SetTextColor(0, 0, 0);
                }
            } //fim tpEmis
            $this->pdf->SetTextColor(0, 0, 0);
        }
        return $oldY;
    } //fim zCabecalho

    /**
     * rodapeDACTE
     * Monta o rodape no final da DACTE ( retrato e paisagem )
     *
     * @param number $xInic Posição horizontal canto esquerdo
     * @param number $yFinal Posição vertical final para impressão
     */
    protected function zRodape($x, $y)
    {
        $texto = "Impresso em  " . date('d/m/Y   H:i:s');
        $w = $this->wPrint - 4;
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x-1, $y+2, $w, 4, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->nomeDesenvolvedor . ' - '. $this->siteDesenvolvedor;
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x-50, $y+2, $w, 4, $texto, $aFont, 'T', 'R', 0, $this->siteDesenvolvedor);
    } //fim zRodape

    /**
     * zTomador
     * Monta o campo com os dados do remetente na DACTE. ( retrato  e paisagem  )
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zTomador($x = 0, $y = 0)
    {
        // PATCH 17: desenho comum em DacteLeiauteTrait::zTomadorQuadro
        $this->zTomadorQuadro($x, $y);
    } //fim da função tomadorDACTE

    /**
     * zCompValorServ
     * Monta o campo com os componentes da prestação de serviços.
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zCompValorServ($x = 0, $y = 0)
    {
        $oldX = $x;
        $oldY = $y;
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            $maxW = $this->wPrint - $this->wCanhoto;
        }
        $w = $maxW;
        $h = 25;
        $texto = 'COMPONENTES DO VALOR DA PRESTAÇÃO DO SERVIÇO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'C', 1, '');
        $y += 3.4;
        $this->pdf->Line($x, $y, $w + 1, $y);
        $texto = 'NOME';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yIniDados = $y;
        $x = $w * 0.14;
        $texto = 'VALOR';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x = $w * 0.28;
        $this->pdf->Line($x, $y, $x, $y + 21.5);
        $texto = 'NOME';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x = $w * 0.42;
        $texto = 'VALOR';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x = $w * 0.56;
        $this->pdf->Line($x, $y, $x, $y + 21.5);
        $texto = 'NOME';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x = $w * 0.70;
        $texto = 'VALOR';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x = $w * 0.86;
        $this->pdf->Line($x, $y, $x, $y + 21.5);
        $y += 1;
        $texto = 'VALOR TOTAL DO SERVIÇO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'C', 0, '');
        $texto = number_format((float)$this->getTagValue($this->vPrest, "vTPrest"), 2, ",", ".");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 9,
            'style' => 'B');
        $this->pTextBox($x, $y + 4, $w * 0.14, $h, $texto, $aFont, 'T', 'C', 0, '');
        $y += 10;
        $this->pdf->Line($x, $y, $w + 1, $y);
        $y += 1;
        $texto = 'VALOR A RECEBER';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'C', 0, '');
        $texto = number_format((float)$this->getTagValue($this->vPrest, "vRec"), 2, ",", ".");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 9,
            'style' => 'B');
        $this->pTextBox($x, $y + 4, $w * 0.14, $h, $texto, $aFont, 'T', 'C', 0, '');
        $auxX = $oldX;
        $yIniDados += 4;

        // PATCH 17: até 12 componentes em 4 linhas; até 18 em 6 linhas mais juntas;
        // os demais vão para a folha de continuação (Comp é 0..n no schema).
        $this->zComponentes($oldX, $oldY, $yIniDados, $w);
    } //fim da função compValorDACTE

    /**
     * zImpostos
     * Monta o campo com os dados do remetente na DACTE. ( retrato  e paisagem  )
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zImpostos($x = 0, $y = 0)
    {
        $oldX = $x;
        $oldY = $y;
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            $maxW = $this->wPrint - $this->wCanhoto;
        }
        $w = $maxW;
        $h = 13;
        $texto = 'INFORMAÇÕES RELATIVAS AO IMPOSTO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'C', 1, '');

        $y += 3.4;
        $this->pdf->Line($x, $y, $w + 1, $y);
        $texto = 'SITUAÇÃO TRIBUTÁRIA';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.26, $h, $texto, $aFont, 'T', 'L', 0, '');

        $x += $w * 0.26;
        $this->pdf->Line($x, $y, $x, $y + 9.5);
        $texto = 'BASE DE CALCULO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'L', 0, '');

        $wCol02=0.18;
        $x += $w * $wCol02;
        $this->pdf->Line($x, $y, $x, $y + 9.5);
        $texto = 'ALÍQ ICMS';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');

        $x += $w * $wCol02;
        $this->pdf->Line($x, $y, $x, $y + 9.5);
        $texto = 'VALOR ICMS';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');

        $x += $w * $wCol02;
        $this->pdf->Line($x, $y, $x, $y + 9.5);
        $texto = '% RED. BC ICMS';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');

        /*$x += $w * 0.14;
        $this->pdf->Line($x, $y, $x, $y + 9.5);
        $texto = 'ICMS ST';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'L', 0, '');
         * */

        $x = $oldX;
        $y = $y + 4;
        $texto = $this->getTagValue($this->ICMS, "CST");
        switch ($texto) {
            case '00':
                $texto = "00 - Tributação normal ICMS";
                break;
            case '20':
                $texto = "20 - Tributação com BC reduzida do ICMS";
                break;
            case '40':
                $texto = "40 - ICMS isenção";
                break;
            case '41':
                $texto = "41 - ICMS não tributada";
                break;
            case '51':
                $texto = "51 - ICMS diferido";
                break;
            case '60':
                $texto = "60 - ICMS cobrado anteriormente por substituição tributária";
                break;
            case '90':
                $texto = "90 - ICMS outros";
                break;
        }
        $texto = $this->getTagValue($this->ICMSSN, "indSN") == 1 ? 'Simples Nacional' : $texto;
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * 0.26, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.26;

        $texto = !empty($this->ICMS->getElementsByTagName("vBC")->item(0)->nodeValue) ?
            number_format((float)$this->getTagValue($this->ICMS, "vBC"), 2, ",", ".") : '';
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * $wCol02;

        $texto = !empty($this->ICMS->getElementsByTagName("pICMS")->item(0)->nodeValue) ?
            number_format((float)$this->getTagValue($this->ICMS, "pICMS"), 2, ",", ".") : '';
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * $wCol02;

        $texto = !empty($this->ICMS->getElementsByTagName("vICMS")->item(0)->nodeValue) ?
            number_format((float)$this->getTagValue($this->ICMS, "vICMS"), 2, ",", ".") : '';
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * $wCol02;

        $texto = !empty($this->ICMS->getElementsByTagName("pRedBC")->item(0)->nodeValue) ?
            number_format((float)$this->getTagValue($this->ICMS, "pRedBC"), 2, ",", ".").'%' :'';
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');

        /*$x += $w * 0.14;
        $texto = '';
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'L', 0, '');*/
    } //fim da função compValorDACTE

    /**
     * zGeraChaveAdicCont
     *
     * @return string chave
     */
    protected function zGeraChaveAdicCont()
    {
        //cUF tpEmis CNPJ vNF ICMSp ICMSs DD  DV
        // Quantidade de caracteres  02   01      14  14    01    01  02 01
        $forma = "%02d%d%s%014d%01d%01d%02d";
        $cUF = $this->ide->getElementsByTagName('cUF')->item(0)->nodeValue;
        $CNPJ = "00000000000000" . $this->emit->getElementsByTagName('CNPJ')->item(0)->nodeValue;
        $CNPJ = substr($CNPJ, -14);
        $vCT = number_format((float)$this->getTagValue($this->vPrest, "vRec"), 2, "", "") * 100;
        $ICMS_CST = $this->getTagValue($this->ICMS, "CST");
        switch ($ICMS_CST) {
            case '00':
            case '20':
                $ICMSp = '1';
                $ICMSs = '2';
                break;
            case '40':
            case '41':
            case '51':
            case '90':
                $ICMSp = '2';
                $ICMSs = '2';
                break;
            case '60':
                $ICMSp = '2';
                $ICMSs = '1';
                break;
        }
        $dd = $this->ide->getElementsByTagName('dEmi')->item(0)->nodeValue;
        $rpos = strrpos($dd, '-');
        $dd = substr($dd, $rpos + 1);
        $chave = sprintf($forma, $cUF, $this->tpEmis, $CNPJ, $vCT, $ICMSp, $ICMSs, $dd);
        $chave = $chave . $this->pModulo11($chave);
        return $chave;
    } //fim zGeraChaveAdicCont

    /**
     * zInfPrestacaoServico
     * Monta o campo com das informações da prestação do serviço
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zInfPrestacaoServico($x = 0, $y = 0)
    {
        // PATCH 17: quadro de 53 mm em qualquer modal (o montaDACTE já avança 53;
        // com 35 mm sobrava um vão), quantidade e descrição presas às colunas e os
        // documentos referenciados (infDocRef, 0..n) — antes descartados — em
        // tabela; os que não couberem vão para a folha de continuação.
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $w = $maxW;
        $h = 53;
        $fRot = $this->formatPadrao;
        $fVal = $this->formatNegrito;
        $docs = $this->zLinhasDocs();
        $titulo = 'INFORMAÇÕES DA PRESTAÇÃO DO SERVIÇO';
        $this->pTextBox($x, $y, $w, $h, $titulo, $fRot, 'T', 'C', 1, '');
        $this->pdf->Line($x, $y + 3.4, $w + 1, $y + 3.4);
        $xd = $x + $w * 0.14;
        $hServ = $docs ? 9 : $h - 3.4;
        $this->pdf->Line($xd, $y + 3.4, $xd, $y + 3.4 + $hServ);
        $this->pTextBox($x, $y + 3.4, $w * 0.14, 3, 'QUANTIDADE', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($xd + 1, $y + 3.4, $w * 0.5, 3, 'DESCRIÇÃO DO SERVIÇO PRESTADO', $fRot, 'T', 'L', 0, '');
        $q = $this->getTagValue($this->infQ->item(0), "qCarga");
        $this->zCaixaTexto($x, $y + 7.4, $xd - $x - 0.3, 4.5, $q === '' ? '' : number_format((float)$q, 4, ",", "."), $fVal);
        $this->zCampo($xd + 0.5, $y + 7.4, $x + $w - $xd - 1, 4.5, $this->getTagValue($this->infServico->item(0), "xDescServ"), $fVal, 'DESCRIÇÃO DO SERVIÇO PRESTADO');
        if ($docs) {
            $y0 = $y + 3.4 + $hServ;
            $passo = 3.5;
            $cabem = 2 * (int) floor(($y + $h - 0.5 - ($y0 + 6.4)) / $passo);
            $this->docsPendentes = array_slice($docs, $cabem);
            $this->pdf->Line($x, $y0, $w + 1, $y0);
            $this->zCaixaTexto($x, $y0, $w, 3.4, 'DOCUMENTOS REFERENCIADOS' . ($this->docsPendentes ? ' (CONTINUA NA PRÓXIMA FOLHA)' : ''), $fRot, 'C');
            $this->pdf->Line($x, $y0 + 3.4, $w + 1, $y0 + 3.4);
            $this->pdf->Line($x + $w * 0.5, $y0 + 3.4, $x + $w * 0.5, $y + $h);
            $fDado = array('font' => $this->fontePadrao, 'size' => 7, 'style' => '');
            $cols = $this->zColunasDocs();
            foreach ([0, 1] as $g) {
                foreach ($cols as $c) {
                    $this->zCaixaTexto($x + ($g + $c[1]) * $w * 0.5, $y0 + 3.4, $c[2] * $w * 0.5, 3, $c[0], $fRot);
                }
            }
            foreach (array_slice($docs, 0, $cabem) as $i => $item) {
                $yl = $y0 + 6.4 + intdiv($i, 2) * $passo;
                foreach ($cols as $k => $c) {
                    $this->zCaixaTexto($x + ($i % 2 + $c[1]) * $w * 0.5, $yl, $c[2] * $w * 0.5 - 0.3, $passo, $item[$k], $fDado);
                }
            }
        }
        $this->zCabecalho(1, 1, '1', (string) $this->totalFolhas);
        return $y + $h;
    } //fim da função zInfPrestacaoServico

    /** PATCH 17: colunas dos documentos referenciados (meia largura). */
    protected function zColunasDocs()
    {
        return [['SÉRIE/SUBSÉRIE', 0, 0.20], ['NÚMERO / CHAVE BP-e', 0.20, 0.46], ['EMISSÃO', 0.66, 0.15], ['VALOR', 0.81, 0.19]];
    }

    /** @return list<list<string>> documentos referenciados (infDocRef) */
    protected function zLinhasDocs()
    {
        $linhas = [];
        foreach ($this->dom->getElementsByTagName('infDocRef') as $d) {
            $ch = $this->getTagValue($d, 'chBPe');
            if ($ch !== '') {
                $linhas[] = ['BP-e', $ch, '', ''];
                continue;
            }
            $serie = $this->getTagValue($d, 'serie');
            $sub = $this->getTagValue($d, 'subserie');
            $v = $this->getTagValue($d, 'vDoc');
            $linhas[] = [
                trim($serie . ($sub !== '' ? '/' . $sub : '')),
                $this->getTagValue($d, 'nDoc'),
                $this->pSimpleGetDate($d, 'dEmi'),
                $v === '' ? '' : number_format((float)$v, 2, ',', '.'),
            ];
        }
        return $linhas;
    }

    /** PATCH 17: nova folha de continuação com o cabeçalho completo e o rodapé. */
    protected function zNovaFolhaContinuacao()
    {
        $this->pdf->AddPage($this->orientacao, $this->papel);
        $this->zCabecalho(1, 1, (string) $this->pdf->page, (string) $this->totalFolhas);
        if ($this->orientacao == 'P') {
            $this->zRodape(2, $this->hPrint - 2);
        } else {
            $this->zRodape(5, $this->hPrint + 2.3);
        }
    }

    /**
     * zDocCompl
     * Monta o campo com os dados do remetente na DACTE.
     *
     * @param number $x Posição horizontal canto esquerdo
     * @param number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zDocCompl($x = 0, $y = 0)
    {
        $oldX = $x;
        $oldY = $y;
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            $maxW = $this->wPrint - $this->wCanhoto;
        }
        $w = $maxW;
        $h = 80;
        $texto = 'DETALHAMENTO DO CT-E COMPLEMENTADO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'C', 1, '');
        $descr1 = 'CHAVE DO CT-E COMPLEMENTADO';
        $descr2 = 'VALOR COMPLEMENTADO';
        $y += 3.4;
        $this->pdf->Line($x, $y, $w + 1, $y);
        $texto = $descr1;
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yIniDados = $y;
        $x += $w * 0.37;
        $texto = $descr2;
        $aFont = $this->formatPadrao;
        $this->pTextBox($x - 8, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.13;
        $this->pdf->Line($x, $y, $x, $y + 76.5);
        $texto = $descr1;
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.3;
        $texto = $descr2;
        $aFont = $this->formatPadrao;
        $this->pTextBox($x + 8, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $auxX = $oldX;
        $yIniDados += 4;
        if ($auxX > $w * 0.90) {
            $yIniDados = $yIniDados + 4;
            $auxX = $oldX;
        }
        $texto = $this->chaveCTeRef;
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($auxX, $yIniDados, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = number_format((float)$this->getTagValue($this->vPrest, "vTPrest"), 2, ",", ".");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($w * 0.40, $yIniDados, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
    } //fim da função zDocCompl

    /**
     * zObs
     * Monta o campo com os dados do remetente na DACTE.
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zObs($x = 0, $y = 0)
    {
        // PATCH 17: xObs + infAdFisco (antes concatenado e descartado) + CT-e OS
        // substituído + campos que não couberam; ajusta ao quadro e continua na
        // folha seguinte. O corte em "Motorista:" (2ª caixa fixa a 11,5 mm, que
        // sobrepunha a 1ª quando o texto era longo) foi removido.
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $w = $maxW;
        $h = 18.8;
        $this->pTextBox($x, $y, $w, $h, 'OBSERVAÇÕES GERAIS', $this->formatPadrao, 'T', 'C', 1, '');
        $this->pdf->Line($x, $y + 3.4, $w + 1, $y + 3.4);
        $partes = [];
        foreach ($this->compl as $c) {
            foreach ($c->childNodes as $n) {
                if ($n->nodeType === XML_ELEMENT_NODE && $n->localName === 'xObs') {
                    $partes[] = $n->nodeValue;
                }
            }
        }
        $partes[] = $this->getTagValue($this->imp, "infAdFisco");
        $sub = $this->dom->getElementsByTagName('infCteSub')->item(0);
        if ($sub) {
            $partes[] = 'CT-E OS SUBSTITUÍDO: ' . ($this->getTagValue($sub, 'chCte') ?: $this->getTagValue($sub, 'chCTe'));
        }
        $partes = array_merge($partes, $this->excedentes);
        $texto = implode("\n", array_filter(array_map('trim', $partes), 'strlen'));
        $aFont = array('font' => $this->fontePadrao, 'size' => 7.5, 'style' => '');
        $this->zTextoComContinuacao($x, $y + 3.4, $w, $h - 3.6, $texto, $aFont, 'OBSERVAÇÕES GERAIS');
        return $y + $h;
    } //fim da função obsDACTE

    /**
     * zSeguro
     * Monta o campo com os dados de seguro do CT-e OS.
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zSeguro($x = 0, $y = 0)
    {
        // PATCH 17: seguradora e apólice saíam sempre em branco (texto '' fixo);
        // seg é 0..n — o 1º no quadro, os demais na folha de continuação.
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $w = $maxW;
        $h = 13;
        $fRot = $this->formatPadrao;
        $this->pTextBox($x, $y, $w, $h, 'SEGURO DA VIAGEM', $fRot, 'T', 'C', 1, '');
        $y += 3.4;
        $this->pdf->Line($x, $y, $w + 1, $y);
        $cols = [[$x, $w * 0.33, 'RESPONSÁVEL'], [$x + $w * 0.33, $w * 0.33, 'NOME DA SEGURADORA'], [$x + $w * 0.66, $w * 0.34, 'NÚMERO DA APÓLICE']];
        foreach ($cols as $i => [$xc, $wc, $rot]) {
            if ($i > 0) {
                $this->pdf->Line($xc, $y, $xc, $y + 9.5);
            }
            $this->pTextBox($xc, $y, $wc, 3, $rot, $fRot, 'T', 'L', 0, '');
        }
        $resp = ['4' => 'Emitente', '5' => 'Tomador do Serviço'];
        $extra = [];
        foreach ($this->dom->getElementsByTagName('seg') as $k => $seg) {
            $valores = [
                $resp[$this->getTagValue($seg, 'respSeg')] ?? $this->getTagValue($seg, 'respSeg'),
                $this->getTagValue($seg, 'xSeg'),
                $this->getTagValue($seg, 'nApol'),
            ];
            if ($k === 0) {
                foreach ($cols as $i => [$xc, $wc, $rot]) {
                    $this->zCampo($xc, $y + 4, $wc - 0.3, 5, $valores[$i], $this->formatNegrito, "SEGURO - $rot", 'L', 'T', 'SEGURO DA VIAGEM');
                }
            } else {
                $extra[] = implode(' | ', array_filter($valores, 'strlen'));
            }
        }
        if ($extra) {
            $this->textosPendentes['SEGURO DA VIAGEM'] = trim(($this->textosPendentes['SEGURO DA VIAGEM'] ?? '') . "\n" . implode("\n", $extra));
        }
        return $y + $h;
    } //fim da função zSeguro

    /**
     * Stub para modal Aereo (modal=02). A sped-da nao implementa este modal
     * no CT-e OS; o switch de modais chama zModalAereo mas o metodo nao existia,
     * causando fatal error. Retorna a posicao Y atual sem renderizar quadro
     * especifico (mesma estrategia do Patch 5 em Dacte.php).
     */
    protected function zModalAereo($x = 0, $y = 0)
    {
        // PATCH 17: era stub vazio (Patch 8) — os dados do grupo aereo sumiam
        return $this->zModalGenerico($x, $y, 'DADOS ESPECÍFICOS DO MODAL AÉREO', $this->dom->getElementsByTagName('aereo')->item(0));
    }

    /**
     * Stub para modal Dutoviario (modal=06). Mesma justificativa de zModalAereo.
     */
    protected function zModalDutoviario($x = 0, $y = 0)
    {
        // PATCH 17: era stub vazio — os dados do grupo duto sumiam
        return $this->zModalGenerico($x, $y, 'DADOS ESPECÍFICOS DO MODAL DUTOVIÁRIO', $this->dom->getElementsByTagName('duto')->item(0));
    }

    /**
     * zModalRod
     * Monta o campo com os dados do remetente na DACTE. ( retrato  e paisagem  )
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zModalRod($x = 0, $y = 0)
    {
        $oldX = $x;
        $oldY = $y;
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            $maxW = $this->wPrint - $this->wCanhoto;
        }
        $w = $maxW;
        $h = 13;
        $texto = 'DADOS ESPECÍFICOS DO MODAL RODOVIÁRIO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'C', 1, '');

        $y += 3.4;
        $this->pdf->Line($x, $y, $w + 1, $y);
        $texto = 'TERMO DE AUTORIZAÇÃO DE FRETAMENTO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.26, $h, $texto, $aFont, 'T', 'L', 0, '');

        $x += $w * 0.26;
        $this->pdf->Line($x, $y, $x, $y + 9.5);
        $texto = 'Nº DE REGISTRO ESTADUAL';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'L', 0, '');

        $wCol02=0.18;
        $x += $w * $wCol02;
        $this->pdf->Line($x, $y, $x, $y + 9.5);
        $texto = 'PLACA DO VEÍCULO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');

        $x += $w * $wCol02;
        $this->pdf->Line($x, $y, $x, $y + 9.5);
        $texto = 'RENAVAM DO VEÍCULO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');

        $x += $w * $wCol02;
        $this->pdf->Line($x, $y, $x, $y + 9.5);
        $texto = 'CNPJ/CPF';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');

        $x = $oldX;
        $y = $y + 4;
        $texto = $this->getTagValue($this->rodo, "TAF");
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * 0.26, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.26;

        $texto = $this->getTagValue($this->rodo, "NroRegEstadual");
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * $wCol02;

        $texto = $this->getTagValue($this->veic->item(0), "placa");
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * $wCol02;

        $texto = $this->getTagValue($this->veic->item(0), "RENAVAM");
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * $wCol02;

        $texto = !empty($this->getTagValue($this->veic->item(0), "CPF")) ?
            $this->getTagValue($this->veic->item(0), "CPF") :
            (!empty($this->getTagValue($this->veic->item(0), "CNPJ")) ?
            $this->getTagValue($this->veic->item(0), "CNPJ") : '');
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * $wCol02, $h, $texto, $aFont, 'T', 'L', 0, '');

        /*$x += $w * 0.14;
        $texto = '';
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y, $w * 0.14, $h, $texto, $aFont, 'T', 'L', 0, '');*/
    } //fim da função zModalRod

    /**
     * zModalAquaviario
     * Monta o campo com os dados do remetente na DACTE. ( retrato  e paisagem  )
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zModalAquaviario($x = 0, $y = 0)
    {
        $oldX = $x;
        $oldY = $y;
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            $maxW = $this->wPrint - $this->wCanhoto;
        }
        $w = $maxW;
        $h = 8.5;
        $texto = 'DADOS ESPECÍFICOS DO MODAL AQUAVIÁRIO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w, $h * 3.2, $texto, $aFont, 'T', 'C', 1, '');
        $y += 3.4;
        $this->pdf->Line($x, $y, $w + 1, $y);
        $texto = 'PORTO DE EMBARQUE';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->aquav, "prtEmb");
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3, $w * 0.50 - 0.3, 4.7, $texto, $aFont, 'PORTO DE EMBARQUE', 'L', 'T', 'DADOS ESPECÍFICOS DO MODAL AQUAVIÁRIO'); // PATCH 17
        $x += $w * 0.50;
        $this->pdf->Line($x, $y, $x, $y + 7.7);
        $texto = 'PORTO DE DESTINO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->aquav, "prtDest");
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3, $w * 0.50 - 0.3, 4.7, $texto, $aFont, 'PORTO DE DESTINO', 'L', 'T', 'DADOS ESPECÍFICOS DO MODAL AQUAVIÁRIO'); // PATCH 17
        $y += 8;
        $this->pdf->Line(208, $y, 1, $y);
        $x = 1;
        $texto = 'IDENTIFICAÇÃO DO NAVIO / REBOCADOR';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->aquav, "xNavio");
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3, $w * 0.50 - 0.3, 4.7, $texto, $aFont, 'IDENTIFICAÇÃO DO NAVIO / REBOCADOR', 'L', 'T', 'DADOS ESPECÍFICOS DO MODAL AQUAVIÁRIO'); // PATCH 17
        $x += $w * 0.50;
        $this->pdf->Line($x, $y, $x, $y + 7.7);
        $texto = 'VR DA B. DE CALC. AFRMM';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->aquav, "vPrest");
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3, $w * 0.17 - 0.3, 4.7, $texto, $aFont, 'VR DA B. DE CALC. AFRMM', 'L', 'T', 'DADOS ESPECÍFICOS DO MODAL AQUAVIÁRIO'); // PATCH 17
        $x += $w * 0.17;
        $this->pdf->Line($x, $y, $x, $y + 7.7);
        $texto = 'VALOR DO AFRMM';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->aquav, "vAFRMM");
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3, $w * 0.12 - 0.3, 4.7, $texto, $aFont, 'VALOR DO AFRMM', 'L', 'T', 'DADOS ESPECÍFICOS DO MODAL AQUAVIÁRIO'); // PATCH 17
        $x += $w * 0.12;
        $this->pdf->Line($x, $y, $x, $y + 7.7);
        $texto = 'TIPO DE NAVEGAÇÃO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->aquav, "tpNav");
        switch ($texto) {
            case '0':
                $texto = 'INTERIOR';
                break;
            case '1':
                $texto = 'CABOTAGEM';
                break;
        }
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3, $w * 0.14 - 0.3, 4.7, $texto, $aFont, 'TIPO DE NAVEGAÇÃO', 'L', 'T', 'DADOS ESPECÍFICOS DO MODAL AQUAVIÁRIO'); // PATCH 17
        $x += $w * 0.14;
        $this->pdf->Line($x, $y, $x, $y + 7.7);
        $texto = 'DIREÇÃO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->aquav, "direc");
        switch ($texto) {
            case 'N':
                $texto = 'NORTE';
                break;
            case 'L':
                $texto = 'LESTE';
                break;
            case 'S':
                $texto = 'SUL';
                break;
            case 'O':
                $texto = 'OESTE';
                break;
        }
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3, $w * 0.07 - 0.3, 4.7, $texto, $aFont, 'DIREÇÃO', 'L', 'T', 'DADOS ESPECÍFICOS DO MODAL AQUAVIÁRIO'); // PATCH 17
        $y += 8;
        $this->pdf->Line(208, $y, 1, $y);
        $x = 1;
        $texto = 'IDENTIFICAÇÃO DOS CONTEINERS';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        if ($this->infNF->item(0) !== null && $this->infNF->item(0)->getElementsByTagName('infUnidCarga') !== null) {
            $texto = $this->infNF
                ->item(0)
                ->getElementsByTagName('infUnidCarga')
                ->item(0)
                ->getElementsByTagName('idUnidCarga')
                ->item(0)->nodeValue;
        } elseif ($this->infNFe->item(0) !== null
            && $this->infNFe->item(0)->getElementsByTagName('infUnidCarga') !== null
        ) {
            $texto = $this->infNFe
                ->item(0)
                ->getElementsByTagName('infUnidCarga')
                ->item(0)
                ->getElementsByTagName('idUnidCarga')
                ->item(0)
                ->nodeValue;
        } elseif ($this->infOutros->item(0) !== null
            && $this->infOutros->item(0)->getElementsByTagName('infUnidCarga') !== null
        ) {
            $texto = $this->infOutros
                ->item(0)
                ->getElementsByTagName('infUnidCarga')
                ->item(0)
                ->getElementsByTagName('idUnidCarga')
                ->item(0)
                ->nodeValue;
        } else {
            $texto = '';
        }
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3, $w * 0.50 - 0.3, 4.7, $texto, $aFont, 'IDENTIFICAÇÃO DOS CONTEINERS', 'L', 'T', 'DADOS ESPECÍFICOS DO MODAL AQUAVIÁRIO'); // PATCH 17
        $x += $w * 0.50;
        $this->pdf->Line($x, $y, $x, $y + 7.7);
        $texto = 'IDENTIFICAÇÃO DAS BALSAS';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = '';
        if ($this->getTagValue($this->aquav, "balsa") !== '') {
            foreach ($this->aquav->getElementsByTagName('balsa') as $k => $d) {
                if ($k == 0) {
                    $texto = $this->aquav
                        ->getElementsByTagName('balsa')
                        ->item($k)
                        ->getElementsByTagName('xBalsa')
                        ->item(0)
                        ->nodeValue;
                } else {
                    $texto = $texto
                        . ' / '
                        . $this->aquav
                            ->getElementsByTagName('balsa')
                            ->item($k)
                            ->getElementsByTagName('xBalsa')
                            ->item(0)
                            ->nodeValue;
                }
            }
        }
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3, $w * 0.50 - 0.3, 4.7, $texto, $aFont, 'IDENTIFICAÇÃO DAS BALSAS', 'L', 'T', 'DADOS ESPECÍFICOS DO MODAL AQUAVIÁRIO'); // PATCH 17
    } //fim da função zModalRod

    /**
     * zModalFerr
     * Monta o campo com os dados do remetente na DACTE. ( retrato  e paisagem  )
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zModalFerr($x = 0, $y = 0)
    {
        $oldX = $x;
        $oldY = $y;
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            $maxW = $this->wPrint - $this->wCanhoto;
        }
        $w = $maxW;
        $h = 19.6;
        $texto = 'DADOS ESPECÍFICOS DO MODAL FERROVIÁRIO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'C', 1, '');
        $y += 3.4;
        $this->pdf->Line($x, $y, $w + 1, $y);
        $texto = 'DCL';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 7,
            'style' => 'B');
        $this->pTextBox($x, $y, $w * 0.25, $h, $texto, $aFont, 'T', 'C', 0, '');
        $this->pdf->Line($x + 49.6, $y, $x + 49.6, $y + 3.5);
        $texto = 'VAGÕES';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 7,
            'style' => 'B');
        $this->pTextBox($x + 50, $y, $w * 0.5, $h, $texto, $aFont, 'T', 'C', 0, '');
        $y += 3.4;
        $this->pdf->Line($x, $y, $w + 1, $y);
        // DCL
        $texto = 'ID TREM';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ferrov, "idTrem");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.06;
        $y1 = $y + 12.5;
        $this->pdf->Line($x, $y, $x, $y1);
        $texto = 'NUM';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->rem, "nDoc");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.06;
        $this->pdf->Line($x, $y, $x, $y1);
        $texto = 'SÉRIE';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->rem, "serie");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.06;
        $this->pdf->Line($x, $y, $x, $y1);
        $texto = 'EMISSÃO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->pYmd2dmy($this->getTagValue($this->rem, "dEmi"));
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        // VAGOES
        $x += $w * 0.06;
        $this->pdf->Line($x, $y, $x, $y1);
        $texto = 'NUM';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ferrov, "nVag");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.06;
        $this->pdf->Line($x, $y, $x, $y1);
        $texto = 'TIPO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ferrov, "tpVag");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.06;
        $this->pdf->Line($x, $y, $x, $y1);
        $texto = 'CAPACIDADE';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ferrov, "cap");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.08;
        $this->pdf->Line($x, $y, $x, $y1);
        $texto = 'PESO REAL/TON';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ferrov, "pesoR");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.09;
        $this->pdf->Line($x, $y, $x, $y1);
        $texto = 'PESO BRUTO/TON';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ferrov, "pesoBC");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w * 0.10, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.1;
        $this->pdf->Line($x, $y, $x, $y1);
        $texto = 'IDENTIFICAÇÃO DOS CONTÊINERES';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ferrov, "nCont");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        // FLUXO
        $x = 1;
        $y += 12.9;
        $h1 = $h * 0.5 + 0.27;
        $wa = round($w * 0.103) + 0.5;
        $texto = 'FLUXO FERROVIARIO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $wa, $h1, $texto, $aFont, 'T', 'C', 1, '');
        $texto = $this->getTagValue($this->ferrov, "fluxo");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 7,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $wa, $h1, $texto, $aFont, 'T', 'C', 0, '');
        $y += 10;
        $texto = 'TIPO DE TRÁFEGO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $wa, $h1, $texto, $aFont, 'T', 'C', 1, '');
        $texto = $this->zConvertUnidTrafego($this->getTagValue($this->ferrov, "tpTraf"));
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 7,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $wa, $h1, $texto, $aFont, 'T', 'C', 0, '');
        // Novo Box Relativo a Modal Ferroviário
        $x = 22.5;
        $y += -10.2;
        $texto = 'INFORMAÇÕES DAS FERROVIAS ENVOLVIDAS';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w - 21.5, $h1 * 2.019, $texto, $aFont, 'T', 'C', 1, '');
        $y += 3.4;
        $this->pdf->Line($x, $y, $w + 1, $y);
        $w = $w * 0.2;
        $h = $h * 1.04;
        $texto = 'CÓDIGO INTERNO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ferrov, "cInt");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = 'CNPJ';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y + 6, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ferrov, "CNPJ");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 9, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += 50;
        $texto = 'NOME';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ferrov, "xNome");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 3, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = 'INSCRICAO ESTADUAL';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y + 6, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->ferrov, "IE");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 9, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += 50;
        $texto = 'PARTICIPAÇÃO OUTRA FERROVIA';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y + 6, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = '';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => 'B');
        $this->pTextBox($x, $y + 9, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
    } //fim da função zModalFerr

    /**
     * zCanhoto
     * Monta o campo com os dados do remetente na DACTE.
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zCanhoto($x = 0, $y = 0)
    {
        $this->zhDashedLine($x, $y+2, $this->wPrint, 0.1, 80);
        $y = $y + 2;
        $oldX = $x;
        $oldY = $y;
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            $maxW = $this->wPrint - $this->wCanhoto;
        }
        $w = $maxW - 1;
        $h = 20;
        $y = $y + 1;
        $texto = 'DECLARO QUE RECEBI OS VOLUMES DESTE CONHECIMENTO EM PERFEITO ESTADO ';
        $texto .= 'PELO QUE DOU POR CUMPRIDO O PRESENTE CONTRATO DE TRANSPORTE';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'C', 1, '');
        $y += 3.4;
        $this->pdf->Line($x, $y, $w + 1, $y); // LINHA ABAICO DO TEXTO DECLARO QUE RECEBI...

        $texto = 'NOME';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w * 0.25, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.25;

        $this->pdf->Line($x, $y, $x, $y + 16.5);

        $texto = 'ASSINATURA / CARIMBO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w * 0.25, $h - 3.4, $texto, $aFont, 'B', 'C', 0, '');
        $x += $w * 0.25;

        $this->pdf->Line($x, $y, $x, $y + 16.5);

        $texto = 'TÉRMINO DA PRESTAÇÃO - DATA/HORA' . "\r\n" . "\r\n" . "\r\n". "\r\n";
        $texto .= ' INÍCIO DA PRESTAÇÃO - DATA/HORA';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x + 10, $y, $w * 0.25, $h - 3.4, $texto, $aFont, 'T', 'C', 0, '');
        $x = $oldX;
        $y = $y + 5;

        $this->pdf->Line($x, $y+3, $w * 0.255, $y+3); // LINHA HORIZONTAL ACIMA DO RG ABAIXO DO NOME

        $texto = 'RG';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y+3, $w * 0.33, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.85;

        $this->pdf->Line($x, $y + 11.5, $x, $y - 5); // LINHA VERTICAL PROXIMO AO CT-E

        $texto = "CT-E OS";
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y - 5, $w * 0.15, $h, $texto, $aFont, 'T', 'C', 0, '');
        $texto = "\r\n Nº. DOCUMENTO  " . $this->getTagValue($this->ide, "nCT") . " \n";
        $texto .= "\r\n SÉRIE  " . $this->getTagValue($this->ide, "serie");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y - 8, $w * 0.15, $h, $texto, $aFont, 'C', 'C', 0, '');
        $x = $oldX;
        //$this->zhDashedLine($x, $y + 7.5, $this->wPrint, 0.1, 80);
    } //fim da função canhotoDACTE

    /**
     * zDadosAdic
     * Coloca o grupo de dados adicionais da DACTE.
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @param  number $h altura do campo
     * @return number Posição vertical final
     */
    protected function zDadosAdic($x, $y, $pag, $h)
    {
        // PATCH 17: desenho comum em DacteLeiauteTrait::zDadosAdicQuadro
        return $this->zDadosAdicQuadro($x, $y, 20);
    } //fim zDadosAdic

    /**
     * zhDashedLine
     * Desenha uma linha horizontal tracejada com o FPDF
     *
     * @param  number $x Posição horizontal inicial, em mm
     * @param  number $y Posição vertical inicial, em mm
     * @param  number $w Comprimento da linha, em mm
     * @param  number $h Espessura da linha, em mm
     * @param  number $n Numero de traços na seção da linha com o comprimento $w
     * @return none
     */
    protected function zhDashedLine($x, $y, $w, $h, $n)
    {
        $this->pdf->SetLineWidth($h);
        $wDash = ($w / $n) / 2; // comprimento dos traços
        for ($i = $x; $i <= $x + $w; $i += $wDash + $wDash) {
            for ($j = $i; $j <= ($i + $wDash); $j++) {
                if ($j <= ($x + $w - 1)) {
                    $this->pdf->Line($j, $y, $j + 1, $y);
                }
            }
        }
    } //fim função hDashedLine

    /**
     * zhDashedVerticalLine
     * Desenha uma linha vertical tracejada com o FPDF
     *
     * @param  number $x Posição horizontal inicial, em mm
     * @param  number $y Posição vertical inicial, em mm
     * @param  number $w Comprimento da linha, em mm
     * @param  number $yfinal Espessura da linha, em mm
     * @param  number $n Numero de traços na seção da linha com o comprimento $w
     * @return none
     */
    protected function zhDashedVerticalLine($x, $y, $w, $yfinal, $n)
    {
        $this->pdf->SetLineWidth($w);
        /* Organizando valores */
        if ($y > $yfinal) {
            $aux = $yfinal;
            $yfinal = $y;
            $y = $aux;
        }
        while ($y < $yfinal && $n > 0) {
            $this->pdf->Line($x, $y, $x, $y + 1);
            $y += 3;
            $n--;
        }
    } //fim função hDashedVerticalLine

    /**
     * zFormatCNPJCPF
     * Formata campo CnpjCpf contida na CTe
     *
     * @param  string $field campo cnpjCpf da CT-e
     * @return string
     */
    protected function zFormatCNPJCPF($field)
    {
        if (!isset($field)) {
            return '';
        }
        $cnpj = !empty($field->getElementsByTagName("CNPJ")->item(0)->nodeValue) ?
            $field->getElementsByTagName("CNPJ")->item(0)->nodeValue : "";
        if ($cnpj != "" && $cnpj != "00000000000000") {
            $cnpj = $this->pFormat($cnpj, '###.###.###/####-##');
        } else {
            $cnpj = !empty($field->getElementsByTagName("CPF")->item(0)->nodeValue) ?
                $this->pFormat($field->getElementsByTagName("CPF")->item(0)->nodeValue, '###.###.###.###-##') : '';
        }
        return $cnpj;
    } //fim formatCNPJCPF

    /**
     * zFormatFone
     * Formata campo fone contida na CTe
     *
     * @param  string $field campo fone da CT-e
     * @return string
     */
    protected function zFormatFone($field)
    {
        if (empty($field)) {
            return '';
        }
        $fone = !empty($field->getElementsByTagName("fone")->item(0)->nodeValue) ?
            $field->getElementsByTagName("fone")->item(0)->nodeValue : '';
        $foneLen = strlen($fone);
        // PATCH 17: celular de 11 dígitos saía "(119) 8765-4321"; o schema aceita
        // 6 a 14 dígitos — só formata DDD+8/9 dígitos, o resto sai como veio.
        if ($foneLen === 10 || $foneLen === 11) {
            $fone = '(' . substr($fone, 0, 2) . ') ' . substr($fone, 2, $foneLen - 6) . '-' . substr($fone, -4);
        } elseif ($foneLen > 0 && $foneLen < 10) {
            $fone = substr($fone, 0, $foneLen - 4) . '-' . substr($fone, -4);
        }
        return $fone;
    } //fim formatFone

    /**
     * zUnidade
     * Converte a imformação de peso contida na CTe
     *
     * @param  string $c unidade de trafego extraida da CTe
     * @return string
     */
    protected function zUnidade($c = '')
    {
        switch ($c) {
            case '00':
                $r = 'M3';
                break;
            case '01':
                $r = 'KG';
                break;
            case '02':
                $r = 'TON';
                break;
            case '03':
                $r = 'UN';
                break;
            case '04':
                $r = 'LT';
                break;
            case '05':
                $r = 'MMBTU';
                break;
            default:
                $r = '';
        }
        return $r;
    } //fim unidade

    /**
     * zConvertUnidTrafego
     * Converte a imformação de peso contida na CTe
     *
     * @param  string $U Informação de trafego extraida da CTe
     * @return string
     */
    protected function zConvertUnidTrafego($U = '')
    {
        if ($U) {
            switch ($U) {
                case '0':
                    $stringU = 'Próprio';
                    break;
                case '1':
                    $stringU = 'Mútuo';
                    break;
                case '2':
                    $stringU = 'Rodoferroviário';
                    break;
                case '3':
                    $stringU = 'Rodoviário';
                    break;
            }
            return $stringU;
        }
    } //fim da função zConvertUnidTrafego

    /**
     * zMultiUniPeso
     * Fornece a imformação multiplicação de peso contida na CTe
     *
     * @param  interger $U Informação de peso extraida da CTe
     * @return interger
     */
    protected function zMultiUniPeso($U = '')
    {
        if ($U === "01") {
            // tonelada
            //return 1000;
            return 1;
        }
        return 1; // M3, KG, Unidade, litros, mmbtu
    } //fim da função zMultiUniPeso

    protected function pQRDACTEOS($y = 0)
    {
        $margemInterna = $this->margemInterna;
        $barcode = new Barcode();
        $bobj = $barcode->getBarcodeObj(
            'QRCODE,M',
            $this->qrCodMDFe,
            -4,
            -4,
            'black',
            array(-1, -1, -1, -1)
        )->setBackgroundColor('white');
        $qrcode = $bobj->getPngData();
        // PATCH 17: 26 × 26 mm (mínimo do Anexo II: 25 mm), centrado na coluna nova
        $wQr = 26;
        $hQr = 26;
        $yQr = ($y + 3 + $margemInterna);
        if ($this->orientacao == 'P') {
            $xQr = 181;
        } else {
            $xQr = 250;
        }
        // prepare a base64 encoded "data url"
        $pic = 'data://text/plain;base64,' . base64_encode($qrcode);
        $this->pdf->image($pic, $xQr, $yQr, $wQr, $hQr, 'PNG');
    }
}
