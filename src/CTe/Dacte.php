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

use NFePHP\DA\Legacy\Dom;
use NFePHP\DA\Legacy\Pdf;
use NFePHP\DA\Legacy\Common;

class Dacte extends Common
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
    protected $infCteComp;
    protected $chaveCTeRef;
    protected $tpCTe;
    protected $ide;
    protected $emit;
    protected $enderEmit;
    protected $rem;
    protected $enderReme;
    protected $dest;
    protected $enderDest;
    protected $exped;
    protected $enderExped;
    protected $receb;
    protected $enderReceb;
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
    protected $hDadosAdic = 14; // PATCH 17
    protected $respSeg;
    protected $lota;
    protected $infOutros;
    protected $ICMSSN;
    protected $cteProc;
    protected $protCTe;

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
        $preVisualizar = false
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
        // verifica se foi passa a fonte a ser usada
        if (!empty($fonteDACTE)) {
            $this->fontePadrao = $fonteDACTE;
        }
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
            $this->cteProc = $this->dom->getElementsByTagName("cteProc")->item(0);
            $this->infCte = $this->dom->getElementsByTagName("infCte")->item(0);
            $this->ide = $this->dom->getElementsByTagName("ide")->item(0);
            $this->emit = $this->dom->getElementsByTagName("emit")->item(0);
            $this->enderEmit = $this->dom->getElementsByTagName("enderEmit")->item(0);
            $this->rem = $this->dom->getElementsByTagName("rem")->item(0);
            $this->enderReme = $this->dom->getElementsByTagName("enderReme")->item(0);
            $this->dest = $this->dom->getElementsByTagName("dest")->item(0);
            $this->enderDest = $this->dom->getElementsByTagName("enderDest")->item(0);
            $this->exped = $this->dom->getElementsByTagName("exped")->item(0);
            $this->enderExped = $this->dom->getElementsByTagName("enderExped")->item(0);
            $this->receb = $this->dom->getElementsByTagName("receb")->item(0);
            $this->enderReceb = $this->dom->getElementsByTagName("enderReceb")->item(0);
            $this->infCarga = $this->dom->getElementsByTagName("infCarga")->item(0);
            $this->infQ = $this->dom->getElementsByTagName("infQ");
            $this->seg = $this->dom->getElementsByTagName("seg")->item(0);
            $this->rodo = $this->dom->getElementsByTagName("rodo")->item(0);
            $this->lota = $this->getTagValue($this->rodo, "lota");
            $this->moto = $this->dom->getElementsByTagName("moto")->item(0);
            $this->veic = $this->dom->getElementsByTagName("veic");
            $this->ferrov = $this->dom->getElementsByTagName("ferrov")->item(0);
            // adicionar outros modais
            $this->infCteComp = $this->dom->getElementsByTagName("infCteComp")->item(0);
            $this->chaveCTeRef = $this->getTagValue($this->infCteComp, "chave");
            // PATCH 17: no 3.00/4.00 a chave do CT-e complementado é "chCTe" e pode
            // haver até 10 infCteComp — antes o quadro saía sem chave nenhuma.
            $this->chavesCTeRef = [];
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
            $this->toma4 = $this->dom->getElementsByTagName("toma4")->item(0);
            $this->toma03 = $this->dom->getElementsByTagName("toma03")->item(0);
            // PATCH 17: no leiaute 3.00/4.00 a tag é "toma3" ("toma03" era do 2.00) —
            // sem isto o quadro TOMADOR DO SERVIÇO saía vazio em todo CT-e 4.00.
            if ($this->toma03 === null) {
                $this->toma03 = $this->dom->getElementsByTagName("toma3")->item(0);
            }
            //modal aquaviário
            $this->aquav = $this->dom->getElementsByTagName("aquav")->item(0);
            $tomador = $this->toma03 !== null ? $this->getTagValue($this->toma03, "toma") : '4';
            //0-Remetente;1-Expedidor;2-Recebedor;3-Destinatário;4-Outros
            switch ($tomador) {
                case '0':
                    $this->toma = $this->rem;
                    $this->enderToma = $this->enderReme;
                    break;
                case '1':
                    $this->toma = $this->exped;
                    $this->enderToma = $this->enderExped;
                    break;
                case '2':
                    $this->toma = $this->receb;
                    $this->enderToma = $this->enderReceb;
                    break;
                case '3':
                    $this->toma = $this->dest;
                    $this->enderToma = $this->enderDest;
                    break;
                default:
                    $this->toma = $this->toma4;
                    $this->enderToma = $this->toma4 !== null
                        ? $this->toma4->getElementsByTagName("enderToma")->item(0) : null;
                    break;
            }
            $seguro = $this->getTagValue($this->seg, "respSeg");
            switch ($seguro) {
                case '0':
                    $this->respSeg = 'Remetente';
                    break;
                case '1':
                    $this->respSeg = 'Expedidor';
                    break;
                case '2':
                    $this->respSeg = 'Recebedor';
                    break;
                case '3':
                    $this->respSeg = 'Destinatário';
                    break;
                case '4':
                    $this->respSeg = 'Emitente';
                    break;
                case '5':
                    $this->respSeg = 'Tomador';
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
        // PATCH 17: duas passadas — a 1ª desenha num Pdf descartável só para saber
        // o que não cabe na 1ª folha e quantas folhas de continuação haverá (o
        // "FL n/total" do cabeçalho é desenhado antes de tudo isso ser conhecido).
        if (!$this->planejando) {
            $this->planejando = true;
            $this->montaDACTE($orientacao, $papel, $logoAlign, new Pdf($orientacao === 'L' ? 'L' : 'P', 'mm', $papel));
            $this->totalFolhas = 1 + $this->zContinuacao(false, $this->zColunasDocs(), $this->zColunasComp());
            $this->excedentes = [];
            $this->docsPendentes = [];
            $this->compPendentes = [];
            $this->textosPendentes = [];
            $this->arrayNFe = [];
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
        $r = $this->zRemetente($x, $y);
        $x = $this->wPrint * 0.5 + 2;
        $r = $this->zDestinatario($x, $y);
        $y += 19;
        $x = $xInic;
        $r = $this->zExpedidor($x, $y);
        $x = $this->wPrint * 0.5 + 2;
        $r = $this->zRecebedor($x, $y);
        $y += 19;
        $x = $xInic;
        $r = $this->zTomador($x, $y);
        // PATCH 17: o CT-e substituto (tpCTe 3) tem infCTeNorm e usa o leiaute normal
        // (antes caía no de complemento, com o quadro de chave vazio)
        if ($this->tpCTe == '0' || $this->dom->getElementsByTagName('infCTeNorm')->length > 0) {
            //Normal
            $y += 10;
            $x = $xInic;
            $r = $this->zDescricaoCarga($x, $y);
            $y += 17;
            $x = $xInic;
            $r = $this->zCompValorServ($x, $y);
            $y += 25;
            $x = $xInic;
            $r = $this->zImpostos($x, $y);
            $y += 13;
            $x = $xInic;
            $r = $this->zDocOrig($x, $y);
            if ($this->modal == '1') {
                if ($this->lota == 1) {
                    //$y += 24.95;
                    $y += 35;
                } else {
                    $y += 53;
                }
            } elseif ($this->modal == '3') {
                $y += 37.75;
            } else {
                $y += 24.95;
            }
            $x = $xInic;
            $r = $this->zObs($x, $y);
            $y = $y-6;
            switch ($this->modal) {
                case '1':
                    $y += 17.9;
                    $x = $xInic;
                    $r = $this->zModalRod($x, $y);
                    break;
                case '2':
                    $y += 17.9;
                    $x = $xInic;
                    // TODO fmertins 31/10/14: este método não existe...
                    $r = $this->zModalAereo($x, $y);
                    break;
                case '3':
                    $y += 17.9;
                    $x = $xInic;
                    $r = $this->zModalAquaviario($x, $y);
                    break;
                case '4':
                    $y += 17.9;
                    $x = $xInic;
                    $r = $this->zModalFerr($x, $y);
                    break;
                case '5':
                    $y += 17.9;
                    $x = $xInic;
                    // TODO fmertins 31/10/14: este método não existe...
                    $r = $this->zModalDutoviario($x, $y);
                    break;
                case '6': // PATCH 17: multimodal não tinha quadro
                    $y += 17.9;
                    $x = $xInic;
                    $r = $this->zModalMultimodal($x, $y);
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
            $this->hDadosAdic = 11;
        }
        $x = $xInic;
        // PATCH 17: USO EXCLUSIVO / FISCO ganham a sobra do pé da página (8 → 14 mm;
        // 11 mm no complementar) — ObsCont real de 50 a 800 caracteres cabe sem
        // abrir folha nova; o canhoto desce junto e continua acima do rodapé.
        $r = $this->zDadosAdic($x, $y, $pag, $totPag);

        //$y += 19;
        $y += 10 + ($this->hDadosAdic - 8);
        $y = $this->zCanhoto($x, $y);

        //coloca o rodapé da página
        if ($this->orientacao == 'P') {
            $this->zRodape(2, $this->hPrint - 2);
        } else {
            $this->zRodape($xInic, $this->hPrint + 2.3);
        }
        // PATCH 17: folhas de continuação (documentos, componentes e textos)
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
        //0 - Normal;1 - Subcontratação;2 - Redespacho;3 - Redespacho Intermediário
        switch ($tpServ) {
            case '0':
                $texto = 'Normal';
                break;
            case '1':
                $texto = 'Subcontratação';
                break;
            case '2':
                $texto = 'Redespacho';
                break;
            case '3':
                $texto = 'Redespacho Intermediário';
                break;
            default:
                $texto = $tpServ; // PATCH 17
        }
        $aFont = $this->formatNegrito;
        $this->pTextBox($x + $wa + 4.5, $y1 + 3, $w * 0.5, $h1, $texto, $aFont, 'T', 'C', 0, '', false);
        $this->pdf->Line($w * 0.5, $y1, $w * 0.5, $y1 + $h1);
        //TOMADOR DO SERVIÇO
        $texto = 'TOMADOR DO SERVIÇO';
        $wc = 37;
        $y2 = $y1 + 8;
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($x, $y2, $w * 0.5, $h1, $texto, $aFont, 'T', 'C', 0, '');
        $this->pdf->Line($x, $y1 + 8, $w + 3, $y1 + 8);
        $toma = $this->getTagValue($this->ide, "toma");
        //0-Remetente;1-Expedidor;2-Recebedor;3-Destinatário;4 - Outros
        switch ($toma) {
            case '0':
                $texto = 'Remetente';
                break;
            case '1':
                $texto = 'Expedidor';
                break;
            case '2':
                $texto = 'Recebedor';
                break;
            case '3':
                $texto = 'Destinatário';
                break;
            case '4':
                $texto = 'Outros';
                break;
            default:
                $texto = $toma; // PATCH 17
        }
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y2 + 3, $w * 0.5, $h1, $texto, $aFont, 'T', 'C', 0, '', false);
        //FORMA DE PAGAMENTO
        $texto = 'FORMA DE PAGAMENTO';
        $wd = 36;
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($x + $wa + 4.5, $y2, $w * 0.5, $h1, $texto, $aFont, 'T', 'C', 0, '');
        $forma = $this->getTagValue($this->ide, "forPag");
        //0 - Pago;1 - A pagar;2 - outros
        switch ($forma) {
            case '0':
                $texto = 'Pago';
                break;
            case '1':
                $texto = 'A pagar';
                break;
            case '2':
                $texto = 'Outros';
                break;
            default:
                $texto = $forma; // PATCH 17: forPag não existe no 3.00/4.00 → em branco
        }
        $aFont = $this->formatNegrito;
        $this->pTextBox($x + $wa + 4.5, $y2 + 3, $w * 0.5, $h1, $texto, $aFont, 'T', 'C', 0, '', false);
        //##################################################################
        //coluna direita
        $x += $w + 2;
        $w = round($maxW * 0.335);
        $w1 = $w;
        $h = 11;
        $this->pTextBox($x, $y, $w + 2, $h + 1);
        $texto = "DACTE";
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 10,
            'style' => 'B');
        $this->pTextBox($x, $y + 1, $w, $h, $texto, $aFont, 'T', 'C', 0, '');
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $texto = "Documento Auxiliar do Conhecimento\nde Transporte Eletrônico";
        $h = 10;
        $this->pTextBox($x, $y + 4, $w, $h, $texto, $aFont, 'T', 'C', 0, '', false);
        $x1 = $x + $w + 2;
        $w = round($maxW * 0.22, 0);
        $w2 = $w;
        $h = 11;
        $this->pTextBox($x1, $y, $w + 0.5, $h + 1);
        $texto = "MODAL";
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($x1, $y + 1, $w, $h, $texto, $aFont, 'T', 'C', 0, '');
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
            case '6': // PATCH 17: multimodal saía com o rótulo "MODAL" repetido
                $texto = 'Multimodal';
                break;
        }
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 10,
            'style' => 'B');
        $this->pTextBox($x1, $y + 5, $w, $h, $texto, $aFont, 'T', 'C', 0, '');
        //outra caixa
        $y += 12;
        $h = 9;
        $w = $w1 + $w2 + 2;
        $this->pTextBox($x, $y, $w + 0.5, $h + 1);
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
        //folha
        $xa += $wa;
        $wa = 12;
        $texto = 'FL';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($xa, $y + 1, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        //$texto = '1/1';
        $texto = $pag."/".$totPag;
        $aFont = $this->formatNegrito;
        $this->pTextBox($xa, $y + 5, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $this->pdf->Line($xa + $wa, $y, $xa + $wa, $y + $h + 1);
        //data  hora de emissão
        $xa += $wa;
        $wa = 30;
        $texto = 'DATA E HORA DE EMISSÃO';
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 8,
            'style' => '');
        $this->pTextBox($xa, $y + 1, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $texto = !empty($this->ide->getElementsByTagName("dhEmi")->item(0)->nodeValue) ?
            date('d/m/Y H:i:s', $this->pConvertTime($this->getTagValue($this->ide, "dhEmi"))) : '';
        $aFont = $this->formatNegrito;
        $this->pTextBox($xa, $y + 5, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $this->pdf->Line($xa + $wa, $y, $xa + $wa, $y + $h + 1);
        //ISUF
        $xa += $wa;
        $wa = 32;
        $texto = 'INSC. SUFRAMA DO DESTINATÁRIO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($xa, $y + 1, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        $texto = $this->getTagValue($this->dest, "ISUF");
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 7,
            'style' => 'B');
        $this->pTextBox($xa, $y + 5, $wa, $h, $texto, $aFont, 'T', 'C', 0, '');
        //outra caixa
        $y += $h + 1;
        $h = 23;
        $h1 = 14;
        $this->pTextBox($x, $y, $w + 0.5, $h1);
        //CODIGO DE BARRAS
        $chave_acesso = str_replace('CTe', '', $this->infCte->getAttribute("Id"));
        $bW = 85;
        $bH = 10;
        //codigo de barras
        $this->pdf->SetFillColor(0, 0, 0);
        $this->pdf->Code128($x + (($w - $bW) / 2), $y + 2, $chave_acesso, $bW, $bH);
        $this->pTextBox($x, $y + $h1, $w + 0.5, $h1 - 6);
        $texto = 'CHAVE DE ACESSO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y + $h1, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $aFont = $this->formatNegrito;
        $texto = $this->zChaveEmBlocos($chave_acesso); // PATCH 17: 11 blocos de 4 (Anexo II, §2.4)
        $this->pTextBox($x, $y + $h1 + 3, $w, $h, $texto, $aFont, 'T', 'C', 0, '');
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
        $w = ($maxW * 0.5);
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
                $y = round($this->hPrint * 2 / 3, 0);
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
                if (!isset($this->cteProc)) {
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
        $this->pTextBox($x, $y, $w, 4, $texto, $aFont, 'T', 'L', 0, '');
        $texto = "DacteNFePHP ver. " . $this->version . "  Powered by NFePHP (GNU/GPLv3 GNU/LGPLv3) © www.nfephp.org";
        $aFont = array(
            'font' => $this->fontePadrao,
            'size' => 6,
            'style' => '');
        $this->pTextBox($x, $y, $w, 4, $texto, $aFont, 'T', 'R', 0, 'http://www.nfephp.org');
    } //fim zRodape

    /**
     * zRemetente
     * Monta o campo com os dados do remetente na DACTE. ( retrato  e paisagem  )
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zRemetente($x = 0, $y = 0)
    {
        // PATCH 17: os 4 quadros de participante usam o mesmo desenho (zParte)
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $this->zParte($x, $y, $maxW * 0.5 + 0.5, 'REMETENTE', $this->rem, $this->enderReme);
    } //fim da função remetenteDACTE

    protected function zDestinatario($x = 0, $y = 0)
    {
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $this->zParte($x - 0.5, $y, ($maxW * 0.5) - 0.7, 'DESTINATÁRIO', $this->dest, $this->enderDest);
    } //fim da função destinatarioDACTE

    protected function zExpedidor($x = 0, $y = 0)
    {
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $this->zParte($x, $y, $maxW * 0.5 + 0.5, 'EXPEDIDOR', $this->exped, $this->enderExped);
    } //fim da função expedidorDACTE

    protected function zRecebedor($x = 0, $y = 0)
    {
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $this->zParte($x - 0.5, $y, ($maxW * 0.5) - 0.7, 'RECEBEDOR', $this->receb, $this->enderReceb);
    } //fim da função recebedorDACTE

    /**
     * PATCH 17: quadro de participante (remetente, destinatário, expedidor,
     * recebedor) com 6 linhas de 3 mm em 19 mm. Cada valor fica na própria
     * célula: quebra/reduz a fonte até 4,5 pt e, se nem assim couber (ex.: xLgr
     * de 255 caracteres), sai inteiro nas observações. Endereço = xLgr, nro -
     * xCpl - xBairro (Anexo II, §3), em até 2 linhas da caixa.
     */
    protected function zParte($x, $y, $w, $titulo, $no, $ender)
    {
        $h = 19;
        $x1 = $x + 16;
        $fRot = $this->formatPadrao;
        $fVal = $this->formatNegrito;
        $xCep = $x + $w - 19;
        $xIe = $x + $w - 46;
        $xFone = $x + $w - 26;
        $xFim = $x + $w - 0.3;
        $this->pTextBox($x, $y, $w, $h, $titulo, $fRot, 'T', 'L', 1, '');
        $this->pTextBox($x, $y + 3, $w, $h, 'ENDEREÇO', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($x, $y + 9, $w, $h, 'MUNICÍPIO', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($xCep, $y + 9, 8, 3, 'CEP', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($x, $y + 12, $w, $h, 'CNPJ/CPF', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($xIe, $y + 12, 28, 3, 'INSCRIÇÃO ESTADUAL', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($x, $y + 15, $w, $h, 'PAÍS', $fRot, 'T', 'L', 0, '');
        $this->pTextBox($xFone, $y + 15, 8, 3, 'FONE', $fRot, 'T', 'L', 0, '');
        if (empty($no)) {
            return;
        }
        $this->zCampo($x1, $y, $xFim - $x1, 3, $this->getTagValue($no, "xNome"), $fVal, "$titulo - NOME");
        $this->zCampo($x1, $y + 3, $xFim - $x1, 6, $this->zEnderecoTexto($ender), $fVal, "$titulo - ENDEREÇO");
        $mun = $this->getTagValue($ender, "xMun");
        $uf = $this->getTagValue($ender, "UF");
        $this->zCampo($x1, $y + 9, $xCep - $x1 - 0.3, 3, $mun . ($uf !== '' ? ' - ' . $uf : ''), $fVal, "$titulo - MUNICÍPIO");
        $cep = $this->getTagValue($ender, "CEP");
        $this->zCaixaTexto($xCep + 5.5, $y + 9, $xFim - $xCep - 5.5, 3, $cep !== '' ? $this->pFormat($cep, "#####-###") : '', $fVal);
        $this->zCaixaTexto($x1, $y + 12, $xIe - $x1 - 0.3, 3, $this->zFormatCNPJCPF($no), $fVal);
        $this->zCampo($xIe + 27.5, $y + 12, $xFim - $xIe - 27.5, 3, $this->getTagValue($no, "IE"), $fVal, "$titulo - INSCRIÇÃO ESTADUAL");
        $pais = $this->getTagValue($ender, "xPais");
        $this->zCampo($x1, $y + 15, $xFone - $x1 - 0.3, 3, $pais !== '' ? $pais : 'BRASIL', $fVal, "$titulo - PAÍS");
        $this->zCaixaTexto($xFone + 7, $y + 15, $xFim - $xFone - 7, 3, $this->zFormatFone($no), $fVal);
    }

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
     * zDescricaoCarga
     * Monta o campo com os dados do remetente na DACTE. ( retrato  e paisagem  )
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zDescricaoCarga($x = 0, $y = 0)
    {
        // PATCH 17: cada valor preso à sua coluna (antes as 3 colunas de medida e a
        // seguradora usavam a largura da página inteira e invadiam as vizinhas) e
        // as medidas além das 4 primeiras (infQ é 1..n) vão para as observações.
        $oldX = $x;
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $w = $maxW;
        $h = 17;
        $fRot = $this->formatPadrao;
        $fRot5 = array('font' => $this->fontePadrao, 'size' => 5, 'style' => '');
        $fVal = $this->formatNegrito;
        $this->pTextBox($x, $y, $w, $h, 'PRODUTO PREDOMINANTE', $fRot, 'T', 'L', 1, '');
        $x1 = $w * 0.56;
        $x2 = $w * 0.8;
        $this->zCampo($x, $y + 2.8, $x1 - $x - 0.3, 5, $this->getTagValue($this->infCarga, "proPred"), $fVal, 'PRODUTO PREDOMINANTE');
        $this->pdf->Line($x1, $y, $x1, $y + 8);
        $this->pTextBox($x1 + 1, $y, $x2 - $x1 - 1, 3, 'OUTRAS CARACTERÍSTICAS DA CARGA', $fRot, 'T', 'L', 0, '');
        $this->zCampo($x1 + 0.5, $y + 3, $x2 - $x1 - 0.8, 5, $this->getTagValue($this->infCarga, "xOutCat"), $fVal, 'OUTRAS CARACTERÍSTICAS DA CARGA');
        $this->pdf->Line($x2, $y, $x2, $y + 8);
        $this->pTextBox($x2 + 1, $y, $w - $x2, 3, 'VALOR TOTAL DA MERCADORIA', $fRot, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->infCarga, "vCarga") == "" ?
            $this->getTagValue($this->infCarga, "vMerc") : $this->getTagValue($this->infCarga, "vCarga");
        $texto = number_format((float)$texto, 2, ",", ".");
        $this->zCaixaTexto($x2 + 0.5, $y + 3, $x + $w - $x2 - 0.8, 5, $texto, $fVal);
        $y += 8;
        $this->pdf->Line($x, $y, $w + 1, $y);
        // 3 colunas TP MED / UN. MED
        $cols = [[$x, $w * 0.12], [$w * 0.12, $w * 0.24], [$w * 0.24, $w * 0.36]];
        foreach ($cols as $i => [$c0, $c1]) {
            if ($i > 0) {
                $this->pdf->Line($c0, $y, $c0, $y + 9);
            }
            $this->pTextBox($c0, $y, $c1 - $c0, 3, 'TP MED /UN. MED', $fRot5, 'T', 'L', 0, '');
            $q = $this->infQ->item($i);
            if ($q === null || $this->getTagValue($q, "qCarga") === '') {
                continue;
            }
            $cUnid = $this->getTagValue($q, "cUnid");
            $texto = $this->getTagValue($q, "tpMed") . "\n"
                . number_format((float)$this->getTagValue($q, "qCarga") / $this->zMultiUniPeso($cUnid), 3, ".", "")
                . ' ' . $this->zUnidade($cUnid);
            $this->zCampo($c0, $y + 3, $c1 - $c0 - 0.3, 5.8, $texto, $fVal, 'QUANTIDADE DA CARGA');
        }
        $xc = $w * 0.36;
        $this->pdf->Line($xc, $y, $xc, $y + 9);
        $this->pTextBox($xc, $y, $w * 0.09, 3, 'CUBAGEM(M3)', $fRot, 'T', 'L', 0, '');
        $texto = '';
        if ($this->getTagValue($this->infQ->item(0), "cUnid") == '00') {
            $qCarga = $this->getTagValue($this->infQ->item(0), "qCarga");
            $texto = !empty($qCarga) ? number_format((float)$qCarga, 3, ",", ".") : '';
        }
        $this->zCaixaTexto($xc, $y + 3, $w * 0.09 - 0.3, 5.8, $texto, $fVal);
        $xv = $w * 0.45;
        $this->pdf->Line($xv, $y, $xv, $y + 9);
        $this->pTextBox($xv, $y, $w * 0.08, 3, 'QTDE(VOL)', $fRot, 'T', 'L', 0, '');
        $qCarga = $this->getTagValue($this->infQ->item(3), "qCarga");
        $texto = !empty($qCarga) ? number_format((float)$qCarga, 3, ",", ".") : '';
        $this->zCaixaTexto($xv, $y + 3, $w * 0.08 - 0.3, 5.8, $texto, $fVal);
        // medidas que não têm coluna: a 4ª sai só com a quantidade em QTDE(VOL) —
        // se não for UNIDADE, vai completa às observações; da 5ª em diante, todas
        $extra = [];
        for ($i = 3; $i < $this->infQ->length; $i++) {
            $q = $this->infQ->item($i);
            if ($i === 3 && $this->getTagValue($q, "cUnid") === '03') {
                continue; // 4ª medida em UNIDADE: é a QTDE(VOL) do quadro, nada se perde
            }
            $extra[] = trim($this->getTagValue($q, "tpMed") . ' '
                . number_format((float)$this->getTagValue($q, "qCarga"), 3, ",", ".") . ' '
                . $this->zUnidade($this->getTagValue($q, "cUnid")));
        }
        if ($extra) {
            $this->excedentes[] = 'QUANTIDADES DA CARGA (CONTINUAÇÃO): ' . implode('; ', $extra);
        }
        $xs = $w * 0.53;
        $xFim = $x + $w - 0.3;
        $this->pdf->Line($xs, $y, $xs, $y + 9);
        $this->pTextBox($xs, $y, 31, 3, 'NOME DA SEGURADORA', $fRot, 'T', 'L', 0, '');
        $this->zCampo($xs + 23, $y, $xFim - $xs - 23, 3, $this->getTagValue($this->seg, "xSeg"), $fVal, 'NOME DA SEGURADORA');
        $y += 3;
        $this->pdf->Line($xs, $y, $w + 1, $y);
        $xa = $w * 0.68;
        $xb = $w * 0.85;
        $this->pTextBox($xs, $y, $xa - $xs, 3, 'RESPONSÁVEL', $fRot, 'T', 'L', 0, '');
        $this->zCaixaTexto($xs, $y + 3, $xa - $xs - 0.3, 3, $this->respSeg, $fVal);
        $this->pdf->Line($xa, $y, $xa, $y + 6);
        $this->pTextBox($xa, $y, $xb - $xa, 3, 'NÚMERO DA APOLICE', $fRot, 'T', 'L', 0, '');
        $this->zCampo($xa, $y + 3, $xb - $xa - 0.3, 3, $this->getTagValue($this->seg, "nApol"), $fVal, 'NÚMERO DA APÓLICE');
        $this->pdf->Line($xb, $y, $xb, $y + 6);
        $this->pTextBox($xb, $y, $xFim - $xb, 3, 'NÚMERO DA AVERBAÇÃO', $fRot, 'T', 'L', 0, '');
        $this->zCampo($xb, $y + 3, $xFim - $xb, 3, $this->getTagValue($this->seg, "nAver"), $fVal, 'NÚMERO DA AVERBAÇÃO');
    } //fim da função zDescricaoCarga

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
        // PATCH consultadanfe (Patch 8): CT-e sem <imp>/<ICMS> deixa $this->ICMS null.
        // Sem esta guarda, o getTagValue($this->ICMS, ...) abaixo gera fatal error.
        // Pula o bloco de imposto e avanca a posicao Y pela altura do quadro.
        if ($this->ICMS === null) {
            return $oldY + $h;
        }
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
        // PATCH 17: Simples Nacional (ICMSSN/indSN=1) saía "90 - ICMS outros1"; e
        // ICMS60/ICMSOutraUF ficavam com BC/alíquota/valor em branco — o Anexo II
        // manda usar vBC|vBCSTRet|vBCOutraUF, pICMS|pICMSSTRet|pICMSOutraUF etc.
        if ($this->getTagValue($this->ICMSSN, "indSN") == '1') {
            $texto = 'Simples Nacional';
        }
        $aFont = $this->formatNegrito;
        $this->zCaixaTexto($x, $y, $w * 0.26 - 0.3, 5, $texto, $aFont);
        $x += $w * 0.26;
        $colunas = [
            ['vBC', 'vBCSTRet', 'vBCOutraUF'],
            ['pICMS', 'pICMSSTRet', 'pICMSOutraUF'],
            ['vICMS', 'vICMSSTRet', 'vICMSOutraUF'],
            ['pRedBC', 'pRedBCOutraUF'],
        ];
        foreach ($colunas as $i => $tags) {
            $valor = '';
            foreach ($tags as $tag) {
                $v = $this->getTagValue($this->ICMS, $tag);
                if ($v !== '') {
                    $valor = number_format((float)$v, 2, ",", ".") . ($i === 3 ? '%' : '');
                    break;
                }
            }
            $this->zCaixaTexto($x, $y, $w * $wCol02 - 0.3, 5, $valor, $aFont);
            $x += $w * $wCol02;
        }

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
     * zDocOrig
     * Monta o campo com os documentos originarios.
     *
     * PATCH 17: todas as linhas (infNF, infNFe, infDCe, infOutros) entram numa
     * lista única; a 1ª folha mostra quantas couberem no quadro e o resto vai
     * para as folhas de continuação com as mesmas colunas (Anexo II, §2.12).
     * Antes: só 15 NF-e na 1ª folha, a 16ª sumia (o laço da continuação
     * começava no índice 16), mais de ~137 saíam abaixo da página, infNF e
     * infOutros não tinham limite e atravessavam os quadros de baixo.
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zDocOrig($x = 0, $y = 0)
    {
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $w = $maxW;
        if ($this->modal == '1') {
            $h = $this->lota == 1 ? 35 : 53;
        } elseif ($this->modal == '3') {
            $h = 37.6;
        } else {
            // o quadro tinha 35 mm, mas as observações começam 24,95 mm abaixo
            // (montaDACTE) — e a linha do meio já era de 21,5 mm
            $h = 24.95;
        }
        $linhas = $this->zLinhasDocs();
        $passo = $this->zPassoDocs($linhas);
        $yIni = $y + 6.4;
        $cabem = 2 * max(0, (int) floor(($y + $h - 0.5 - $yIni) / $passo));
        $this->docsPendentes = array_slice($linhas, $cabem);
        $titulo = 'DOCUMENTOS ORIGINÁRIOS' . ($this->docsPendentes ? ' (CONTINUA NA PRÓXIMA FOLHA)' : '');
        $this->pTextBox($x, $y, $w, $h, $titulo, $this->formatPadrao, 'T', 'C', 1, '');
        $this->pdf->Line($x, $y + 3.4, $w + 1, $y + 3.4);
        $this->pdf->Line($x + $w * 0.49, $y + 3.4, $x + $w * 0.49, $y + $h);
        $fRot = $this->formatPadrao;
        $fDado = array('font' => $this->fontePadrao, 'size' => 7, 'style' => '');
        $cols = $this->zColunasDocs();
        foreach ([0, 1] as $g) {
            foreach ($cols as $c) {
                $this->zCaixaTexto($x + $g * $w * 0.49 + $c[1] * $w * 0.5, $y + 3.4, $c[2] * $w * 0.5, 3, $c[0], $fRot);
            }
        }
        foreach (array_slice($linhas, 0, $cabem) as $i => $item) {
            $g = $i % 2;
            $yl = $yIni + intdiv($i, 2) * $passo;
            foreach ($cols as $k => $c) {
                $this->zCaixaTexto($x + $g * $w * 0.49 + $c[1] * $w * 0.5, $yl, $c[2] * $w * 0.5 - 0.3, $passo, $item[$k], $fDado);
            }
        }
        $this->zCabecalho(1, 1, '1', (string) $this->totalFolhas);
        return $y + $h;
    } //fim da função zDocOrig

    /** Colunas TIPO DOC | CNPJ/CHAVE/OBS | SÉRIE/NRO. DOCUMENTO (relativas a meia largura). */
    protected function zColunasDocs()
    {
        return [['TIPO DOC', 0, 0.14], ['CNPJ/CHAVE/OBS', 0.14, 0.56], ['SÉRIE/NRO. DOCUMENTO', 0.70, 0.30]];
    }

    /** Linha de 3,5 mm; 4,8 mm (3 linhas de texto) quando há infOutros com descrição. */
    protected function zPassoDocs($linhas)
    {
        return $this->infOutros->length > 0 ? 4.8 : 3.5;
    }

    /** @return list<list<string>> [tipo, CNPJ/chave/obs, série/número] de cada documento */
    protected function zLinhasDocs()
    {
        $linhas = [];
        $cnpjRem = $this->zFormatCNPJCPF($this->rem);
        foreach ($this->infNF as $nf) {
            $linhas[] = [
                $this->getTagValue($nf, 'mod'),
                $cnpjRem,
                $this->getTagValue($nf, 'serie') . '/' . $this->getTagValue($nf, 'nDoc'),
            ];
        }
        foreach (['infNFe' => 'NF-e', 'infDCe' => 'DC-e'] as $tag => $tipo) {
            foreach ($this->dom->getElementsByTagName($tag) as $d) {
                $chave = $this->getTagValue($d, 'chave');
                $this->arrayNFe[] = $chave;
                $linhas[] = [$tipo, $chave, substr($chave, 22, 3) . '/' . substr($chave, 25, 9)];
            }
        }
        $tipos = ['00' => '00 - Declaração', '10' => '10 - Dutoviário', '59' => '59 - CF-e SAT',
            '65' => '65 - NFC-e', '99' => '99 - Outros'];
        foreach ($this->infOutros as $o) {
            $tpDoc = $this->getTagValue($o, 'tpDoc');
            $desc = $this->getTagValue($o, 'descOutros');
            $vDoc = $this->getTagValue($o, 'vDocFisc');
            $obs = trim(($desc !== '' ? $desc . ' ' : '')
                . $this->pSimpleGetDate($o, 'dEmi', 'Emissão: ') . ' '
                . ($vDoc !== '' ? 'Valor: ' . number_format((float) $vDoc, 2, ',', '.') . ' ' : '')
                . $this->pSimpleGetDate($o, 'dPrev', 'Entrega: '));
            $linhas[] = [$tipos[$tpDoc] ?? $tpDoc, $obs, $this->getTagValue($o, 'nDoc')];
        }
        return $linhas;
    }

    /**
     * zDocOrigContinuacao — PATCH 17: substituído pelas folhas de continuação
     * genéricas (DacteLeiauteTrait::zContinuacao). Mantido vazio por compatibilidade.
     */
    protected function zDocOrigContinuacao($x = 0, $y = 0)
    {
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
     * Detalhamento do CT-e complementado (tpCTe 1) ou anulado (tpCTe 2, leiaute 3.00).
     * PATCH 17: no 3.00/4.00 a chave é chCTe (até 10 infCteComp) — antes saía em branco.
     *
     * @param number $x Posição horizontal canto esquerdo
     * @param number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zDocCompl($x = 0, $y = 0)
    {
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $w = $maxW;
        $h = 80;
        $anulacao = $this->tpCTe == '2';
        $chaves = $this->chavesCTeRef;
        $anu = $this->dom->getElementsByTagName('infCteAnu')->item(0);
        if ($anulacao) {
            $chaves = $anu ? [$this->getTagValue($anu, 'chCte') ?: $this->getTagValue($anu, 'chCTe')] : [];
        }
        $texto = $anulacao ? 'DETALHAMENTO DO CT-E ANULADO' : 'DETALHAMENTO DO CT-E COMPLEMENTADO';
        $this->pTextBox($x, $y, $w, $h, $texto, $this->formatPadrao, 'T', 'C', 1, '');
        $this->pdf->Line($x, $y + 3.4, $w + 1, $y + 3.4);
        $this->pdf->Line($x + $w * 0.5, $y + 3.4, $x + $w * 0.5, $y + $h);
        $rotChave = $anulacao ? 'CHAVE DO CT-E ANULADO' : 'CHAVE DO CT-E COMPLEMENTADO';
        $rotValor = $anulacao ? 'DATA DE EMISSÃO' : 'VALOR COMPLEMENTADO';
        $fDado = array('font' => $this->fontePadrao, 'size' => 8, 'style' => '');
        foreach ([0, 1] as $g) {
            $xg = $x + $g * $w * 0.5;
            $this->pTextBox($xg, $y + 3.4, $w * 0.33, 3, $rotChave, $this->formatPadrao, 'T', 'L', 0, '');
            $this->pTextBox($xg + $w * 0.36, $y + 3.4, $w * 0.14, 3, $rotValor, $this->formatPadrao, 'T', 'L', 0, '');
        }
        $valor = $anulacao
            ? ($anu ? $this->pSimpleGetDate($anu, 'dEmi') : '')
            : number_format((float)$this->getTagValue($this->vPrest, "vTPrest"), 2, ",", ".");
        foreach ($chaves as $i => $chave) {
            $g = $i % 2;
            $yl = $y + 7.4 + intdiv($i, 2) * 4;
            $xg = $x + $g * $w * 0.5;
            $this->zCaixaTexto($xg, $yl, $w * 0.36 - 0.3, 4, $chave, $fDado);
            if ($i === 0) { // o XML traz um único valor (vTPrest) para o CT-e de complemento
                $this->zCaixaTexto($xg + $w * 0.36, $yl, $w * 0.14 - 0.3, 4, $valor, $fDado);
            }
        }
        return $y + $h;
    } //fim da função zDocCompl

    /**
     * zObs
     * PATCH 17: OBSERVAÇÕES = xObs + infAdFisco + chave do CT-e substituído +
     * campos que não couberam no próprio quadro. A fonte se ajusta ao quadro
     * (piso 4,5 pt) e o que não couber continua na folha seguinte, com aviso.
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function zObs($x = 0, $y = 0)
    {
        $maxW = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        $w = $maxW;
        $h = 12;
        $this->pTextBox($x, $y, $w, $h, 'OBSERVAÇÕES', $this->formatPadrao, 'T', 'C', 1, '');
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
        $partes[] = $this->zLocalEntrega();
        $sub = $this->dom->getElementsByTagName('infCteSub')->item(0);
        if ($sub) {
            $partes[] = 'CT-E SUBSTITUÍDO: ' . ($this->getTagValue($sub, 'chCte') ?: $this->getTagValue($sub, 'chCTe'));
        }
        $partes = array_merge($partes, $this->excedentes);
        $texto = implode("\n", array_filter(array_map('trim', $partes), 'strlen'));
        $aFont = array('font' => $this->fontePadrao, 'size' => 7.5, 'style' => '');
        $this->zTextoComContinuacao($x, $y + 3.4, $w, $h - 3.6, $texto, $aFont, 'OBSERVAÇÕES');
        return $y + $h;
    } //fim da função obsDACTE

    /**
     * zLocalEntrega
     *
     * @return string
     */
    protected function zLocalEntrega()
    {
        // PATCH 17: CT-e sem <dest> (ex.: redespacho intermediário, CT-e de
        // substituição) dava fatal aqui — 12 XMLs reais do corpus de 2026-11-08.
        if (empty($this->dest)) {
            return "";
        }
        $locEntX = $this->dest->getElementsByTagName('locEnt');
        if ($locEntX->length > 0) {
            $locEnt = $locEntX->item(0);
            $output = "Entrega: " . $output = $this->zFormatCNPJCPF($locEnt);
            $output .= $this->getTagValue($locEnt, "CPF") . " ";
            $output .= $this->getTagValue($locEnt, "xNome") . " ";
            $output .= $this->getTagValue($locEnt, "xLgr") . " ";
            $output .= $this->getTagValue($locEnt, "nro ") . " ";
            $output .= $this->getTagValue($locEnt, "xCpl") . " ";
            $output .= $this->getTagValue($locEnt, "xBairro") . " ";
            $output .= $this->getTagValue($locEnt, "xMun") . " ";
            $output .= $this->getTagValue($locEnt, "UF") . " ";
            return $output;
        }
        return "";
    } //fim zLocalEntrega

    /**
     * zModalRod
     * Monta o campo com os dados do remetente na DACTE. ( retrato  e paisagem  )
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    /**
     * PATCH 17: modal aéreo (02). Era um stub vazio (Patch 5); agora mostra os
     * campos do Anexo II §3 "INFORMAÇÕES ESPECÍFICAS DO MODAL AÉREO".
     */
    protected function zModalAereo($x = 0, $y = 0)
    {
        $aereo = $this->dom->getElementsByTagName('aereo')->item(0);
        if ($aereo === null) {
            return $y;
        }
        $manu = [];
        foreach ($aereo->getElementsByTagName('cInfManu') as $m) {
            $manu[] = $m->nodeValue;
        }
        $retira = $this->getTagValue($this->ide, 'retira');
        $campos = [
            ['NÚMERO OPERACIONAL DO CONHECIMENTO AÉREO', $this->getTagValue($aereo, 'nOCA')],
            ['NÚMERO DA MINUTA', $this->getTagValue($aereo, 'nMinu')],
            ['DATA PREVISTA DE ENTREGA', $this->pSimpleGetDate($aereo, 'dPrevAereo')],
            ['CLASSE', $this->getTagValue($aereo, 'CL')],
            ['CÓDIGO DA TARIFA', $this->getTagValue($aereo, 'cTar')],
            ['VALOR DA TARIFA', $this->zValorOuVazio($this->getTagValue($aereo, 'vTar'))],
            ['RETIRA', $retira === '0' ? 'SIM' : ($retira === '1' ? 'NÃO' : '')],
            ['DADOS RELATIVOS À RETIRADA DA CARGA', $this->getTagValue($this->ide, 'xDetRetira')],
            ['CARACTERÍSTICA ADICIONAL DO SERVIÇO', $this->getTagValue($this->dom->getElementsByTagName('compl')->item(0), 'xCaracSer')],
            ['DIMENSÃO', $this->getTagValue($aereo, 'xDime')],
            ['INFORMAÇÕES DE MANUSEIO', implode(', ', $manu)],
        ];
        return $this->zQuadroCampos($x, $y, 'DADOS ESPECÍFICOS DO MODAL AÉREO', $campos, 4);
    }

    /** PATCH 17: modal dutoviário (05) — vTar, dIni, dFim do grupo duto (CT-e 4.00). */
    protected function zModalDutoviario($x = 0, $y = 0)
    {
        $duto = $this->dom->getElementsByTagName('duto')->item(0);
        if ($duto === null) {
            return $y;
        }
        $campos = [
            ['VALOR DA TARIFA', $this->getTagValue($duto, 'vTar')],
            ['DATA DE INÍCIO DA PRESTAÇÃO', $this->pSimpleGetDate($duto, 'dIni')],
            ['DATA DE FIM DA PRESTAÇÃO', $this->pSimpleGetDate($duto, 'dFim')],
        ];
        return $this->zQuadroCampos($x, $y, 'DADOS ESPECÍFICOS DO MODAL DUTOVIÁRIO', $campos, 3);
    }

    /** PATCH 17: modal multimodal (06) — COTM, indicador negociável e seguro (Anexo II §3). */
    protected function zModalMultimodal($x = 0, $y = 0)
    {
        $mm = $this->dom->getElementsByTagName('multimodal')->item(0);
        if ($mm === null) {
            return $y;
        }
        $neg = $this->getTagValue($mm, 'indNegociavel');
        $campos = [
            ['NÚMERO DO CERTIFICADO DO OPERADOR DE TRANSPORTE MULTIMODAL', $this->getTagValue($mm, 'COTM')],
            ['INDICADOR NEGOCIÁVEL', $neg === '1' ? 'NEGOCIÁVEL' : ($neg === '0' ? 'NÃO NEGOCIÁVEL' : '')],
            ['NOME DA SEGURADORA', $this->getTagValue($mm, 'xSeg')],
            ['CNPJ DA SEGURADORA', $this->getTagValue($mm, 'CNPJ')],
            ['NÚMERO DA APÓLICE', $this->getTagValue($mm, 'nApol')],
            ['NÚMERO DA AVERBAÇÃO', $this->getTagValue($mm, 'nAver')],
        ];
        return $this->zQuadroCampos($x, $y, 'DADOS ESPECÍFICOS DO TRANSPORTE MULTIMODAL DE CARGAS', $campos, 3);
    }

    protected function zModalRod($x = 0, $y = 0)
    {
        $oldX = $x;
        $oldY = $y;
        $lotacao = '';
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            $maxW = $this->wPrint - $this->wCanhoto;
        }
        $w = $maxW;
        if ($this->modal == '1') {
            $h = $this->lota == 1 ? 12.5 : 3.7;
            $lotacao = $this->lota == 1 ? 'Sim' : 'Não';
        } else {
            $h = 12.5;
        }
        $textolota = $this->lota == 1 ? 'LOTAÇÃO' : 'CARGA FRACIONADA';
        $texto = 'DADOS ESPECÍFICOS DO MODAL RODOVIÁRIO - ' . $textolota;
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w, $h * 3.2, $texto, $aFont, 'T', 'C', 1, '');
        if ($this->lota == 1) {
            $this->pdf->Line($x, $y + 12, $w + 1, $y + 12); // LINHA DE BAIXO
        }
        $y += 3.4;
        $this->pdf->Line($x, $y, $w + 1, $y); // LINHA DE CIMA
        $texto = 'RNTRC DA EMPRESA';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->rodo, "RNTRC");
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y + 3, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.23;

        //$this->pdf->Line($x, $y, $x, $y + 8.5);
        $this->pdf->Line($x-20, $y, $x-20, $y + 8.5); // LINHA A FRENTE DA RNTRC DA EMPRESA
        $texto = 'CIOT';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x-20, $y, $w * 0.13, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->getTagValue($this->rodo, "CIOT");
        $aFont = $this->formatNegrito;
        $this->pTextBox($x-20, $y + 3, $w * 0.13, $h, $texto, $aFont, 'T', 'L', 0, '');

        //$this->pdf->Line($x, $y, $x, $y + 8.5);
        $this->pdf->Line($x+10, $y, $x+10, $y + 8.5); // LINHA A FRENTE DO CIOT
        $texto = 'LOTAÇÃO';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x+10, $y, $w * 0.13, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $lotacao;
        $aFont = $this->formatNegrito;
        $this->pTextBox($x+10, $y + 3, $w * 0.13, $h, $texto, $aFont, 'T', 'L', 0, '');

        $x += $w * 0.13;
        $this->pdf->Line($x, $y, $x, $y + 8.5);
        $texto = 'DATA PREVISTA DE ENTREGA';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.15, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->pYmd2dmy($this->getTagValue($this->rodo, "dPrev"));
        $aFont = $this->formatNegrito;
        $this->pTextBox($x, $y + 3, $w * 0.15, $h, $texto, $aFont, 'T', 'L', 0, '');
        $x += $w * 0.15;
        $this->pdf->Line($x, $y, $x, $y + 8.5);
        $h = 25;
        $texto = 'ESTE CONHECIMENTO DE TRANSPORTE ATENDE ' . "\r\n";
        $texto .= ' À LEGISLAÇÃO DE TRANSPORTE RODOVIÁRIO EM VIGOR';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y + 1, $w * 0.50, $h, $texto, $aFont, 'T', 'C', 0, '');
        if ($this->lota == 1) {
            $y += 10;
            $x = 1;
            $texto = 'IDENTIFICAÇÃO DO CONJUNTO TRANSPORTADOR';
            $aFont = $this->formatPadrao;
            $this->pTextBox($x, $y, $w * 0.465, $h, $texto, $aFont, 'T', 'C', 0, '');
            $this->pdf->Line($x, $y + 3.5, $w * 0.465, $y + 3.5);
            $y += 3.5;
            $texto = 'TIPO';
            $aFont = $this->formatPadrao;
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            $yIniDados = $y;
            if (count($this->veic) >= 0) {
                foreach ($this->veic as $k => $d) {
                    $yIniDados = $yIniDados + 3;
                    $texto = $this->getTagValue($this->veic->item($k), "tpVeic");
                    switch ($texto) {
                        case '0':
                            $texto = 'Tração';
                            break;
                        case '1':
                            $texto = 'Reboque';
                            break;
                        default:
                            $texto = ' ';
                    }
                    $aFont = array(
                        'font' => $this->fontePadrao,
                        'size' => 6,
                        'style' => 'B');
                    $this->pTextBox($x, $yIniDados, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
                } //fim foreach
            }
            $x += $w * 0.10;
            $texto = 'PLACA';
            $aFont = $this->formatPadrao;
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            $this->pdf->Line($x, $y, $x, $y + 14);
            $yIniDados = $y;
            if (count($this->veic) >= 0) {
                foreach ($this->veic as $k => $d) {
                    $yIniDados = $yIniDados + 3;
                    $texto = $this->getTagValue($this->veic->item($k), "placa");
                    $aFont = array(
                        'font' => $this->fontePadrao,
                        'size' => 6,
                        'style' => 'B');
                    $this->pTextBox($x, $yIniDados, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
                }
            }
            $x += $w * 0.13;
            $texto = 'UF';
            $aFont = $this->formatPadrao;
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            $this->pdf->Line($x, $y, $x, $y + 23);
            $yIniDados = $y;
            if (count($this->veic) >= 0) {
                foreach ($this->veic as $k => $d) {
                    $yIniDados = $yIniDados + 3;
                    $texto = $this->getTagValue($this->veic->item($k), "UF");
                    $aFont = array(
                        'font' => $this->fontePadrao,
                        'size' => 6,
                        'style' => 'B');
                    $this->pTextBox($x, $yIniDados, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
                }
            }
            $x += $w * 0.03;
            $texto = 'RNTRC';
            $aFont = $this->formatPadrao;
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            $this->pdf->Line($x, $y, $x, $y + 14);
            $yIniDados = $y;
            if (count($this->veic) >= 0) {
                foreach ($this->veic as $k => $d) {
                    $yIniDados = $yIniDados + 3;
                    $texto = $this->getTagValue($this->veic->item($k), "RNTRC");
                    $aFont = array(
                        'font' => $this->fontePadrao,
                        'size' => 6,
                        'style' => 'B');
                    $this->pTextBox($x, $yIniDados, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
                }
            }
            $y += 14;
            $x = 1;
            $texto = 'NOME DO MOTORISTA';
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 5,
                'style' => '');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            $this->pdf->Line($x, $y, $w + 1, $y);
            $texto = !empty($this->moto) ? $this->getTagValue($this->moto, "xNome") : '';
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 7,
                'style' => 'B');
            $this->pTextBox($x, $y + 3, $w * 0.25, $h, $texto, $aFont, 'T', 'L', 0, '');
            $x += $w * 0.23;
            $texto = 'CPF MOTORISTA';
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 5,
                'style' => '');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            //$texto = !empty($this->moto) ? $this->getTagValue($this->moto, "CPF") : '';  // CPF SEM MASCARA
            $texto = !empty($this->moto) ? $this->zFormatCNPJCPF($this->moto) : '';            // CPF COM MASCARA
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 7,
                'style' => 'B');
            $this->pTextBox($x, $y + 3, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            $x += $w * 0.23;
            $texto = 'IDENTIFICAÇÃO DOS LACRES EM TRANSITO';
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 5,
                'style' => '');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            $this->pdf->Line($x, $y, $x, $y - 18.7);
            $this->pdf->Line($x, $y, $x, $y + 9);
            $x = $w * 0.465;
            $y -= 16;
            $texto = 'INFORMAÇÕES REFERENTES AO VALE PEDÁGIO';
            $aFont = $this->formatPadrao;
            $this->pTextBox($x, $y, $w * 0.5, $h, $texto, $aFont, 'T', 'C', 0, '');
            $this->pdf->Line($x, $y + 4, $w + 1, $y + 4);
            $y += 4;
            $texto = 'CNPJ FORNECEDOR';
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 5,
                'style' => '');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            $this->pdf->Line($x, $y + 4, $w + 1, $y + 4);
            $y += 4;
            $texto = 'NUMERO COMPROVANTE';
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 5,
                'style' => '');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            $this->pdf->Line($x, $y + 4, $w + 1, $y + 4);
            $y += 4;
            $texto = 'CNPJ RESPONSÁVEL';
            $aFont = array(
                'font' => $this->fontePadrao,
                'size' => 5,
                'style' => '');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        }
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
        $texto = $this->zValorOuVazio($this->getTagValue($this->aquav, "vPrest"));
        $aFont = $this->formatNegrito;
        $this->zCampo($x, $y + 3, $w * 0.17 - 0.3, 4.7, $texto, $aFont, 'VR DA B. DE CALC. AFRMM', 'L', 'T', 'DADOS ESPECÍFICOS DO MODAL AQUAVIÁRIO'); // PATCH 17
        $x += $w * 0.17;
        $this->pdf->Line($x, $y, $x, $y + 7.7);
        $texto = 'VALOR DO AFRMM';
        $aFont = $this->formatPadrao;
        $this->pTextBox($x, $y, $w * 0.23, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = $this->zValorOuVazio($this->getTagValue($this->aquav, "vAFRMM"));
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
        if ($this->infNF->item(0) !== null && $this->infNF->item(0)->getElementsByTagName('infUnidCarga')->length > 0) {
            $texto = $this->infNF
                ->item(0)
                ->getElementsByTagName('infUnidCarga')
                ->item(0)
                ->getElementsByTagName('idUnidCarga')
                ->item(0)->nodeValue;
        } elseif ($this->infNFe->item(0) !== null
            && $this->infNFe->item(0)->getElementsByTagName('infUnidCarga')->length > 0
        ) {
            $texto = $this->infNFe
                ->item(0)
                ->getElementsByTagName('infUnidCarga')
                ->item(0)
                ->getElementsByTagName('idUnidCarga')
                ->item(0)
                ->nodeValue;
        } elseif ($this->infOutros->item(0) !== null
            && $this->infOutros->item(0)->getElementsByTagName('infUnidCarga')->length > 0
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

        $texto = "CT-E";
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
        return $this->zDadosAdicQuadro($x, $y, $this->hDadosAdic);
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
        if (!isset($field) || is_null($field)) {
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
}
