<?php

namespace NFePHP\DA\NFe;

use InvalidArgumentException;
use NFePHP\DA\Legacy\Dom;
use NFePHP\DA\Legacy\Pdf;
use NFePHP\DA\Legacy\Common;

class Danfe extends Common
{
    use \NFePHP\DA\Common\XmlSanitizerTrait;

    const FPDF_FONTPATH = 'font/';
    const SIT_CANCELADA = 1;
    const SIT_DENEGADA = 2;
    const SIT_DPEC = 3;
    const SIT_NONE = 0;

    /**
     * alinhamento padrão do logo (C-Center)
     *
     * @var string
     */
    public $logoAlign = 'C';
    /**
     * Posição
     * @var float
     */
    public $yDados = 0;
    /**
     * Situação
     * @var integer
     */
    public $situacaoExterna = 0;
    /**
     * Numero DPEC
     *
     * @var string
     */
    public $numero_registro_dpec = '';
    /**
     * quantidade de canhotos a serem montados, geralmente 1 ou 2
     *
     * @var integer
     */
    public $qCanhoto = 1;

    //###########################################################
    // INÍCIO ATRIBUTOS DE PARÂMETROS DE EXIBIÇÃO
    //###########################################################

    /**
     * Parâmetro para exibir ou ocultar os valores do PIS/COFINS.
     * @var boolean
     */
    public $exibirPIS = true;
    /**
     * Parâmetro para exibir ou ocultar os valores do ICMS Interestadual e Valor Total dos Impostos.
     * @var boolean
     */
    public $exibirIcmsInterestadual = true;
    /**
     * Parâmetro para exibir ou ocultar o texto sobre valor aproximado dos tributos.
     * @var boolean
     */
    public $exibirValorTributos = true;
    /**
     * Parâmetro para exibir ou ocultar o texto adicional sobre a forma de pagamento
     * e as informações de fatura/duplicata.
     * @var boolean
     */
    public $exibirTextoFatura = false;
    /**
     * Parâmetro do controle se deve concatenar automaticamente informações complementares
     * na descrição do produto, como por exemplo, informações sobre impostos.
     * @var boolean
     */
    public $descProdInfoComplemento = true;
    /**
     * Parâmetro do controle se deve gerar quebras de linha com "\n" a partir de ";" na descrição do produto.
     * @var boolean
     */
    public $descProdQuebraLinha = true;

    //###########################################################
    //PROPRIEDADES DA CLASSE
    //###########################################################

    /**
     * objeto fpdf()
     * @var object
     */
    protected $pdf;
    /**
     * XML NFe
     * @var string
     */
    protected $xml;
    /**
     * path para logomarca em jpg
     * @var string
     */
    protected $logomarca = '';
    /**
     * mesagens de erro
     * @var string
     */
    protected $errMsg = '';
    /**
     * status de erro true um erro ocorreu false sem erros
     * @var boolean
     */
    protected $errStatus = false;
    /**
     * orientação da DANFE
     * P-Retrato ou L-Paisagem
     * @var string
     */
    protected $orientacao = 'P';
    /**
     * formato do papel
     * @var string
     */
    protected $papel = 'A4';
    /**
     * destino do arquivo pdf
     * I-borwser, S-retorna o arquivo, D-força download, F-salva em arquivo local
     * @var string
     */
    protected $destino = 'I';
    /**
     * diretorio para salvar o pdf com a opção de destino = F
     * @var string
     */
    protected $pdfDir = '';
    /**
     * Nome da Fonte para gerar o DANFE
     * @var string
     */
    protected $fontePadrao = 'Times';
    /**
     * versão
     * @var string
     */
    protected $version = '2.2.8';
    /**
     * Texto
     * @var string
     */
    protected $textoAdic = '';
    /**
     * PATCH 9 (consultadanfe): alturas do cabeçalho, do quadro de impostos e do
     * título da grade de itens, em mm. Os padrões são o leiaute clássico;
     * custom/DanfeRtc (NT 2026.010) aumenta. Mesmo desenho do PR #686 do upstream.
     * @var int
     */
    protected $hCabecalho = 47;
    protected $hImposto = 18;
    protected $hCabecalhoItens = 4;
    /**
     * PATCH 18 (consultadanfe): índices das duplicatas desenhadas no quadro
     * FATURA/DUPLICATA (as demais vão para os dados adicionais) e o texto dos
     * dados adicionais que não coube na folha 1 (sai em folhas de continuação).
     * @var int[]
     */
    protected $dupGrade = [];
    protected $textoAdicContinuacao = '';
    /** PATCH 18: nItem => parte da descrição que não coube numa folha */
    protected $descContinua = [];
    /**
     * Largura
     * @var float
     */
    protected $wAdic = 0;
    /**
     * largura imprimivel, em milímetros
     * @var float
     */
    protected $wPrint;
    /**
     * Comprimento (altura) imprimivel, em milímetros
     * @var float
     */
    protected $hPrint;
    /**
     * largura do canhoto (25mm) apenas para a formatação paisagem
     * @var float
     */
    protected $wCanhoto = 25;
    /**
     * Formato chave
     * @var string
     */
    protected $formatoChave = "#### #### #### #### #### #### #### #### #### #### ####";
    /**
     * quantidade de itens já processados na montagem do DANFE
     * @var integer
     */
    protected $qtdeItensProc;

    /**
     * Document
     * @var DOMDocument
     */
    protected $dom;
    /**
     * Node
     * @var DOMNode
     */
    protected $infNFe;
    /**
     * Node
     * @var DOMNode
     */
    protected $ide;
    /**
     * Node
     * @var DOMNode
     */
    protected $entrega;
    /**
     * Node
     * @var DOMNode
     */
    protected $retirada;
    /**
     * Node
     * @var DOMNode
     */
    protected $emit;
    /**
     * Node
     * @var DOMNode
     */
    protected $dest;
    /**
     * Node
     * @var DOMNode
     */
    protected $enderEmit;
    /**
     * Node
     * @var DOMNode
     */
    protected $enderDest;
    /**
     * Node
     * @var DOMNode
     */
    protected $det;
    /**
     * Node
     * @var DOMNode
     */
    protected $cobr;
    /**
     * Node
     * @var DOMNode
     */
    protected $dup;
    /**
     * Node
     * @var DOMNode
     */
    protected $ICMSTot;
    /**
     * Node
     * @var DOMNode
     */
    protected $ISSQNtot;
    /**
     * Node
     * @var DOMNode
     */
    protected $transp;
    /**
     * Node
     * @var DOMNode
     */
    protected $transporta;
    /**
     * Node
     * @var DOMNode
     */
    protected $veicTransp;
    /**
     * Node reboque
     * @var DOMNode
     */
    protected $reboque;
    /**
     * Node infAdic
     * @var DOMNode
     */
    protected $infAdic;
    /**
     * Tipo de emissão
     * @var integer
     */
    protected $tpEmis;
    /**
     * Node infProt
     * @var DOMNode
     */
    protected $infProt;
    /**
     * 1-Retrato/ 2-Paisagem
     * @var integer
     */
    protected $tpImp;
    /**
     * Node compra
     * @var DOMNode
     */
    protected $compra;
    /**
     * ativa ou desativa o modo de debug
     * @var integer
     */
    protected $debugMode = 2;
    /**
     * Creditos para integrador
     * @var string
     */
    protected $creditos = '';
    /*
     * PATCH 19 (fork): propriedades que eram criadas dinamicamente (deprecated no
     * PHP 8.2). Públicas, como as dinâmicas eram.
     */
    public $nfeProc;
    public $situacao_externa;

    /**
     * __construct
     *
     * @name  __construct
     * @param string  $docXML      Conteúdo XML da NF-e (com ou sem a tag nfeProc)
     * @param string  $sOrientacao (Opcional) Orientação da impressão P-retrato L-Paisagem
     * @param string  $sPapel      Tamanho do papel (Ex. A4)
     * @param string  $sPathLogo   Caminho para o arquivo do logo
     * @param string  $sDestino    Estabelece a direção do envio do documento PDF I-browser D-browser com download S-
     * @param string  $sDirPDF     Caminho para o diretorio de armazenamento dos arquivos PDF
     * @param string  $fonteDANFE  Nome da fonte alternativa do DAnfe
     * @param integer $mododebug   0-Não 1-Sim e 2-nada (2 default)
     */
    public function __construct(
        $docXML = '',
        $sOrientacao = '',
        $sPapel = '',
        $sPathLogo = '',
        $sDestino = 'I',
        $sDirPDF = '',
        $fonteDANFE = '',
        $mododebug = 2
    ) {
        //set_time_limit(1800);
        if (is_numeric($mododebug)) {
            $this->debugMode = $mododebug;
        }
        if ($mododebug == 1) {
            //ativar modo debug
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
        }
        if ($mododebug == 0) {
            //desativar modo debug
            error_reporting(0);
            ini_set('display_errors', 'Off');
        }
        $this->orientacao   = $sOrientacao;
        $this->papel        = $sPapel;
        $this->pdf          = '';
        $this->xml          = $docXML;
        $this->logomarca    = $sPathLogo;
        $this->destino      = $sDestino;
        $this->pdfDir       = $sDirPDF;
        // verifica se foi passa a fonte a ser usada
        if (empty($fonteDANFE)) {
            $this->fontePadrao = 'Times';
        } else {
            $this->fontePadrao = $fonteDANFE;
        }
        //se for passado o xml
        if (! empty($this->xml)) {
            // PATCH consultadanfe: blindagem 2-camadas contra XMLs mal-formados.
            // Camada 1: wrap em CDATA todos os 28+ campos de texto livre da NFe.
            // Camada 2: se loadXML ainda falhar com erro de parse, escapa todos
            //           os '<' que nao formam tag valida.
            // Motivo: emissores colocam HTML/JSON/JavaScript cru em campos como
            // <infCpl>, <xObs>, <xCorrecao> etc, quebrando o parser.
            $this->xml = self::sanitizeXmlFreeText($this->xml);

            $this->dom = new Dom();
            // Suprime warnings de parse pra detectar problemas via libxml_get_errors
            $useInternalErrors = libxml_use_internal_errors(true);
            $loaded = $this->dom->loadXML($this->xml);
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);

            // Se primeira tentativa falhou: aplica fallback agressivo
            if (!$loaded || !empty($errors)) {
                $this->xml = self::aggressiveEscape($this->xml);
                $this->dom = new Dom();
                $this->dom->loadXML($this->xml);
            }
            $this->nfeProc    = $this->dom->getElementsByTagName("nfeProc")->item(0);
            $this->infNFe     = $this->dom->getElementsByTagName("infNFe")->item(0);
            $this->ide        = $this->dom->getElementsByTagName("ide")->item(0);
            $this->entrega    = $this->dom->getElementsByTagName("entrega")->item(0);
            $this->retirada   = $this->dom->getElementsByTagName("retirada")->item(0);
            $this->emit       = $this->dom->getElementsByTagName("emit")->item(0);
            $this->dest       = $this->dom->getElementsByTagName("dest")->item(0);
            $this->enderEmit  = $this->dom->getElementsByTagName("enderEmit")->item(0);
            $this->enderDest  = $this->dom->getElementsByTagName("enderDest")->item(0);
            $this->det        = $this->dom->getElementsByTagName("det");
            $this->cobr       = $this->dom->getElementsByTagName("cobr")->item(0);
            $this->dup        = $this->dom->getElementsByTagName('dup');
            $this->ICMSTot    = $this->dom->getElementsByTagName("ICMSTot")->item(0);
            $this->ISSQNtot   = $this->dom->getElementsByTagName("ISSQNtot")->item(0);
            $this->transp     = $this->dom->getElementsByTagName("transp")->item(0);
            $this->transporta = $this->dom->getElementsByTagName("transporta")->item(0);
            $this->veicTransp = $this->dom->getElementsByTagName("veicTransp")->item(0);
            $this->reboque    = $this->dom->getElementsByTagName("reboque")->item(0);
            $this->infAdic    = $this->dom->getElementsByTagName("infAdic")->item(0);
            $this->compra     = $this->dom->getElementsByTagName("compra")->item(0);
            $this->tpEmis     = $this->getTagValue($this->ide, "tpEmis");
            $this->tpImp      = $this->getTagValue($this->ide, "tpImp");
            $this->infProt    = $this->dom->getElementsByTagName("infProt")->item(0);
            //valida se o XML é uma NF-e modelo 55, pois não pode ser 65 (NFC-e)
            if ($this->getTagValue($this->ide, "mod") != '55') {
                throw new InvalidArgumentException("O xml do DANFE deve ser uma NF-e modelo 55");
            }
        }
    }

    /**
     * Add the credits to the integrator in the footer message
     * @param string $message
     */
    public function creditsIntegratorFooter($message = '')
    {
        $this->creditos = trim($message);
    }

    /**
     * monta
     *
     * @name   monta
     * @param  string $orientacao
     * @param  string $papel
     * @param  string $logoAlign
     * @return string
     */
    public function monta(
        $orientacao = '',
        $papel = 'A4',
        $logoAlign = 'C',
        $situacaoExterna = self::SIT_NONE,
        $classPdf = false,
        $dpecNumReg = '',
        $margSup = 2,
        $margEsq = 2,
        $margInf = 2
    ) {
        return $this->montaDANFE(
            $orientacao,
            $papel,
            $logoAlign,
            $situacaoExterna,
            $classPdf,
            $dpecNumReg,
            $margSup,
            $margEsq,
            $margInf
        );
    }

    /**
     * printDocument
     *
     * @param  string $nome
     * @param  string $destino
     * @param  string $printer
     * @return object pdf
     */
    public function printDocument($nome = '', $destino = 'I', $printer = '')
    {
        $arq = $this->pdf->Output($nome, $destino);
        if ($destino == 'S') {
            //aqui pode entrar a rotina de impressão direta
        }
        return $arq;
    }

    /**
     * montaDANFE
     * Monta a DANFE conforme as informações fornecidas para a classe durante sua
     * construção. Constroi DANFEs com até 3 páginas podendo conter até 56 itens.
     * A definição de margens e posições iniciais para a impressão são estabelecidas
     * pelo conteúdo da funçao e podem ser modificados.
     *
     * @param  string $orientacao (Opcional) Estabelece a orientação da impressão
     *  (ex. P-retrato), se nada for fornecido será usado o padrão da NFe
     * @param  string $papel      (Opcional) Estabelece o tamanho do papel (ex. A4)
     * @return string O ID da NFe numero de 44 digitos extraido do arquivo XML
     */
    public function montaDANFE(
        $orientacao = '',
        $papel = 'A4',
        $logoAlign = 'C',
        $situacaoExterna = self::SIT_NONE,
        $classPdf = false,
        $depecNumReg = '',
        $margSup = 2,
        $margEsq = 2,
        $margInf = 2
    ) {
        //se a orientação estiver em branco utilizar o padrão estabelecido na NF
        if ($orientacao == '') {
            if ($this->tpImp == '1') {
                $orientacao = 'P';
            } else {
                $orientacao = 'L';
            }
        }
        $this->orientacao = $orientacao;
        $this->papel = $papel;
        $this->logoAlign = $logoAlign;
        $this->situacao_externa = $situacaoExterna;
        $this->numero_registro_dpec = $depecNumReg;
        //instancia a classe pdf
        if ($classPdf) {
            $this->pdf = $classPdf;
        } else {
            $this->pdf = new Pdf($this->orientacao, 'mm', $this->papel);
        }
        //margens do PDF, em milímetros. Obs.: a margem direita é sempre igual à
        //margem esquerda. A margem inferior *não* existe na FPDF, é definida aqui
        //apenas para controle se necessário ser maior do que a margem superior
        // posição inicial do conteúdo, a partir do canto superior esquerdo da página
        $xInic = $margEsq;
        $yInic = $margSup;
        if ($this->orientacao == 'P') {
            if ($papel == 'A4') {
                $maxW = 210;
                $maxH = 297;
            }
        } else {
            if ($papel == 'A4') {
                $maxH = 210;
                $maxW = 297;
                //se paisagem multiplica a largura do canhoto pela quantidade de canhotos
                $this->wCanhoto *= $this->qCanhoto;
            }
        }
        //total inicial de paginas
        $totPag = 1;
        //largura imprimivel em mm: largura da folha menos as margens esq/direita
        $this->wPrint = $maxW-($margEsq*2);
        //comprimento (altura) imprimivel em mm: altura da folha menos as margens
        //superior e inferior
        $this->hPrint = $maxH-$margSup-$margInf;
        // estabelece contagem de paginas
        $this->pdf->aliasNbPages();
        // fixa as margens
        $this->pdf->setMargins($margEsq, $margSup);
        $this->pdf->setDrawColor(0, 0, 0);
        $this->pdf->setFillColor(255, 255, 255);
        // inicia o documento
        $this->pdf->open();
        // adiciona a primeira página
        $this->pdf->addPage($this->orientacao, $this->papel);
        $this->pdf->setLineWidth(0.1);
        $this->pdf->setTextColor(0, 0, 0);

        //##################################################################
        // CALCULO DO NUMERO DE PAGINAS A SEREM IMPRESSAS
        //##################################################################
        //Verificando quantas linhas serão usadas para impressão das duplicatas
        $linhasDup = 0;
        if (($this->dup->length > 0) && ($this->dup->length <= 7)) {
            $linhasDup = 1;
        } elseif (($this->dup->length > 7) && ($this->dup->length <= 14)) {
            $linhasDup = 2;
        } elseif (($this->dup->length > 14) && ($this->dup->length <= 21)) {
            $linhasDup = 3;
        } elseif ($this->dup->length > 21) {
            // chinnonsantos 11/05/2016: Limite máximo de impressão de duplicatas na NFe,
            // só vai ser exibito as 21 primeiras duplicatas (parcelas de pagamento),
            // se não oculpa espaço d+, cada linha comporta até 7 duplicatas.
            $linhasDup = 3;
        }
        // PATCH 18: o quadro desenha até 4 linhas (7 por linha no retrato, 9 na
        // paisagem), mas a reserva parava em 3: da 22ª à 28ª duplicata o quadro
        // invadia os blocos seguintes, e da 29ª em diante sumia. Agora a reserva
        // cobre as linhas desenhadas e o que não cabe no quadro (ou não cabe na
        // célula nem com fonte 4,5) vai para os dados adicionais.
        $textoDupExtra = $this->prepararDuplicatas();
        // PATCH 18: descrições mais altas que uma folha (ver limitarDescricao())
        $this->descContinua = array();
        foreach ($this->det as $itemDet) {
            $this->pDescricaoProduto($itemDet);
        }
        foreach ($this->descContinua as $nItem => $resto) {
            $textoDupExtra = "ITEM $nItem (CONTINUAÇÃO DA DESCRIÇÃO): " . str_replace("\n", ' ', $resto)
                . ($textoDupExtra === '' ? '' : "\n" . $textoDupExtra);
        }
        if ($this->dup->length > 0) {
            $porLinha = $this->orientacao == 'P' ? 7 : 9;
            $linhasGrade = (int) ceil(count($this->dupGrade) / $porLinha);
            // todas no quadro: reserva antiga (igual à de sempre até 21) ou a real, a maior
            $linhasDup = count($this->dupGrade) === $this->dup->length ? max($linhasDup, $linhasGrade) : max(1, $linhasGrade);
        }
        //verifica se será impressa a linha dos serviços ISSQN
        $linhaISSQN = 0;
        if ((isset($this->ISSQNtot)) && ($this->getTagValue($this->ISSQNtot, 'vServ') > 0)) {
            $linhaISSQN = 1;
        }
        //calcular a altura necessária para os dados adicionais
        if ($this->orientacao == 'P') {
            $this->wAdic = round($this->wPrint*0.66, 0);
        } else {
            $this->wAdic = round(($this->wPrint-$this->wCanhoto)*0.5, 0);
        }
        $fontProduto = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
        $this->textoAdic = '';
        if (isset($this->retirada)) {
            $txRetCNPJ = $this->getTagValue($this->retirada, "CNPJ");
            $txRetxLgr = $this->getTagValue($this->retirada, "xLgr");
            $txRetnro = $this->getTagValue($this->retirada, "nro");
            $txRetxCpl = $this->getTagValue($this->retirada, "xCpl", " - ");
            $txRetxBairro = $this->getTagValue($this->retirada, "xBairro");
            $txRetxMun = $this->getTagValue($this->retirada, "xMun");
            $txRetUF = $this->getTagValue($this->retirada, "UF");
            $this->textoAdic .= "LOCAL DE RETIRADA : ".
                    $txRetCNPJ.
                    '-' .
                    $txRetxLgr .
                    ', ' .
                    $txRetnro .
                    ' ' .
                    $txRetxCpl .
                    ' - ' .
                    $txRetxBairro .
                    ' ' .
                    $txRetxMun .
                    ' - ' .
                    $txRetUF .
                    "\r\n";
        }
        //dados do local de entrega da mercadoria
        if (isset($this->entrega)) {
            $txRetCNPJ = $this->getTagValue($this->entrega, "CNPJ");
            $txRetxLgr = $this->getTagValue($this->entrega, "xLgr");
            $txRetnro = $this->getTagValue($this->entrega, "nro");
            $txRetxCpl = $this->getTagValue($this->entrega, "xCpl", " - ");
            $txRetxBairro = $this->getTagValue($this->entrega, "xBairro");
            $txRetxMun = $this->getTagValue($this->entrega, "xMun");
            $txRetUF = $this->getTagValue($this->entrega, "UF");
            if ($this->textoAdic != '') {
                $this->textoAdic .= ". \r\n";
            }
            $this->textoAdic .= "LOCAL DE ENTREGA : ".$txRetCNPJ.'-'.$txRetxLgr.', '.$txRetnro.' '.$txRetxCpl.
               ' - '.$txRetxBairro.' '.$txRetxMun.' - '.$txRetUF."\r\n";
        }
        //informações adicionais
        $this->textoAdic .= $this->pGeraInformacoesDasNotasReferenciadas();
        if (isset($this->infAdic)) {
            $i = 0;
            if ($this->textoAdic != '') {
                $this->textoAdic .= ". \r\n";
            }
            $this->textoAdic .= ! empty($this->getTagValue($this->infAdic, "infCpl")) ?
                'Inf. Contribuinte: ' .
                $this->pAnfavea($this->getTagValue($this->infAdic, "infCpl")) : '';
            $infPedido = $this->pGeraInformacoesDaTagCompra();
            if ($infPedido != "") {
                $this->textoAdic .= $infPedido;
            }
            $this->textoAdic .= $this->getTagValue($this->dest, "email", ' Email do Destinatário: ');
            $this->textoAdic .= ! empty($this->getTagValue($this->infAdic, "infAdFisco")) ?
                "\r\n Inf. fisco: " .
                $this->getTagValue($this->infAdic, "infAdFisco") : '';
            $obsCont = $this->infAdic->getElementsByTagName("obsCont");
            if (isset($obsCont)) {
                foreach ($obsCont as $obs) {
                    $campo =  $obsCont->item($i)->getAttribute("xCampo");
                    $xTexto = ! empty($obsCont->item($i)->getElementsByTagName("xTexto")->item(0)->nodeValue) ?
                        $obsCont->item($i)->getElementsByTagName("xTexto")->item(0)->nodeValue : '';
                    $this->textoAdic .= "\r\n" . $campo . ':  ' . trim($xTexto);
                    $i++;
                }
            }
        }
        //INCLUSO pela NT 2013.003 Lei da Transparência
        //verificar se a informação sobre o valor aproximado dos tributos
        //já se encontra no campo de informações adicionais
        if ($this->exibirValorTributos) {
            $flagVTT = strpos(strtolower(trim($this->textoAdic)), 'valor');
            $flagVTT = $flagVTT || strpos(strtolower(trim($this->textoAdic)), 'vl');
            $flagVTT = $flagVTT && strpos(strtolower(trim($this->textoAdic)), 'aprox');
            $flagVTT = $flagVTT && (strpos(strtolower(trim($this->textoAdic)), 'trib') ||
                    strpos(strtolower(trim($this->textoAdic)), 'imp'));
            $vTotTrib = $this->getTagValue($this->ICMSTot, 'vTotTrib');
            if ($vTotTrib != '' && !$flagVTT) {
                $this->textoAdic .= "\n Valor Aproximado dos Tributos : R$ " . number_format((float)$vTotTrib, 2, ",", ".");
            }
        }
        //fim da alteração NT 2013.003 Lei da Transparência
        $this->textoAdic = str_replace(";", "\n", $this->textoAdic);
        if ($textoDupExtra !== '') {
            $this->textoAdic .= ($this->textoAdic === '' ? '' : "\n") . $textoDupExtra;
        }
        $alinhas = explode("\n", $this->textoAdic);
        $numlinhasdados = 0;
        foreach ($alinhas as $linha) {
            $numlinhasdados += $this->pGetNumLines($linha, $this->wAdic, $fontProduto);
        }
        $hdadosadic = round(($numlinhasdados+3) * $this->pdf->fontSize, 0);
        if ($hdadosadic < 10) {
            $hdadosadic = 10;
        }
        //altura disponivel para os campos da DANFE
        $hcabecalho = $this->hCabecalho;//para cabeçalho (PATCH 9)
        $hdestinatario = 25;//para destinatario
        $hduplicatas = 12;//para cada grupo de 7 duplicatas
        $himposto = $this->hImposto;// para imposto (PATCH 9)
        $htransporte = 25;// para transporte
        $hissqn = 11;// para issqn
        $hfooter = 5;// para rodape
        $hCabecItens = $this->hCabecalhoItens;//cabeçalho dos itens (PATCH 9)
        // PATCH 18: dados adicionais mais altos que o espaço da folha 1 (infCpl de
        // 5.000 + infAdFisco de 2.000 caracteres, obsCont...) empurravam o resto
        // para fora da página (no Clássico, a área de itens ficava com altura
        // negativa e o quadro era desenhado por cima do cabeçalho). Agora a folha 1
        // leva o que cabe e o restante sai em folhas de continuação ao final.
        $this->textoAdicContinuacao = '';
        $nPagCont = 0;
        $hAdicMax = $this->hPrint - 10 - ($hcabecalho + $hdestinatario + ($linhasDup * $hduplicatas)
            + $himposto + $htransporte + ($linhaISSQN * $hissqn) + $hfooter + $hCabecItens
            + $this->pSizeExtraTextoFatura()) - ($this->orientacao == 'P' ? 24 * $this->qCanhoto : 0);
        if ($hdadosadic > $hAdicMax) {
            $hdadosadic = max(10, floor($hAdicMax));
            $aFontAdic = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
            $linhaH = 7 / 72 * 25.4;
            // -1: linha do aviso de continuação
            $cabem = max(1, (int) floor(($hdadosadic - 3 + 0.3) / $linhaH) - 1);
            list($primeira, $resto) = $this->dividirTexto($this->textoAdic, $this->wAdic - 2, $cabem, $aFontAdic);
            if ($resto !== '') {
                $this->textoAdic = $primeira;
                $this->textoAdicContinuacao = $resto;
                $wCont = $this->wPrint - 2;
                // mesma altura do quadro desenhado: o cabeçalho das folhas seguintes mede hCabecalho - 1
                $hBoxCont = $this->hPrint - ($hcabecalho - 1) - $hfooter - 4;
                $porFolha = max(1, (int) floor(($hBoxCont - 3 + 0.3) / $linhaH));
                $nPagCont = (int) ceil($this->contarLinhas($resto, $wCont, $aFontAdic) / $porFolha);
            }
        }
        //alturas disponiveis para os dados
        $hDispo1 = $this->hPrint - 10 - ($hcabecalho +
            $hdestinatario + ($linhasDup * $hduplicatas) + $himposto + $htransporte +
            ($linhaISSQN * $hissqn) + $hdadosadic + $hfooter + $hCabecItens +
            $this->pSizeExtraTextoFatura());
        if ($this->orientacao == 'P') {
            $hDispo1 -= 24 * $this->qCanhoto;//para canhoto
            $w = $this->wPrint;
        } else {
            $hcanhoto = $this->hPrint;//para canhoto
            $w = $this->wPrint - $this->wCanhoto;
        }
        $hDispo2 = $this->hPrint - 10 - ($hcabecalho + $hfooter + $hCabecItens)-4;
        //Contagem da altura ocupada para impressão dos itens
        $fontProduto = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
        // PATCH 9: começa no item 0 (o original pulava o primeiro) e a altura vem
        // de calculeHeight() (gancho para custom/DanfeRtc). Item que não cabe vai
        // para a próxima página, exceto se a página já está vazia (conta inteira,
        // nunca comparação de float: 11.000000000000014 > 11 travava o worker) e
        // com teto de páginas = itens + 1, para não haver laço infinito.
        $i = 0;
        $hUsado = $hCabecItens;
        $w2 = round($w*0.28, 0);
        $hDispo = $hDispo1;
        $totPag = 1;
        $naPagina = 0;
        while ($i < $this->det->length) {
            $hItem = $this->calculeHeight($this->det->item($i), $w2);
            if ($hUsado + $hItem > $hDispo && ($naPagina > 0 || $totPag === 1)
                && $totPag <= $this->det->length) {
                $totPag++;
                $hDispo = $hDispo2;
                $hUsado = $hCabecItens;
                $naPagina = 0;
                // Remove canhoto para páginas secundárias em modo paisagem ('L')
                $w2 = round($this->wPrint*0.28, 0);
                continue; // reavalia o item que não coube nesta página na próxima
            }
            $hUsado += $hItem;
            $naPagina++;
            $i++;
        } //fim da soma das areas de itens usadas
        $qtdeItens = $i; //controle da quantidade de itens no DANFE
        // PATCH 18: folhas de continuação dos dados adicionais vêm depois das de itens
        $totPagItens = $totPag;
        $totPag += $nPagCont;
        if ($nPagCont > 0) {
            $this->textoAdic .= "\n(CONTINUA EM DADOS ADICIONAIS - CONTINUAÇÃO, FOLHA " . ($totPagItens + 1) . ")";
        }
        //montagem da primeira página
        $pag = 1;
        $x = $xInic;
        $y = $yInic;
        //coloca o(s) canhoto(s) da NFe
        if ($this->orientacao == 'P') {
            for ($i = 1; $i <= $this->qCanhoto; $i++) {
                $y = $this->pCanhoto($x, $y);
            }
        } else {
            for ($i = 1; $i <= $this->qCanhoto; $i++) {
                $this->pCanhoto($x, $y);
                $x = 25 * $i;
            }
        }
        //coloca o cabeçalho
        $y = $this->pCabecalhoDANFE($x, $y, $pag, $totPag);
        //coloca os dados do destinatário
        $y = $this->pDestinatarioDANFE($x, $y+1);
        
        
        //Verifica as formas de pagamento da nota fiscal
        $formaPag = array();
        if (isset($this->detPag) && $this->detPag->length > 0) {
            foreach ($this->detPag as $k => $d) {
                $fPag = !empty($this->detPag->item($k)->getElementsByTagName('tPag')->item(0)->nodeValue) ?
                    $this->detPag->item($k)->getElementsByTagName('tPag')->item(0)->nodeValue : '0';
                $formaPag[$fPag] = $fPag;
            }
        }
        //caso tenha boleto imprimir fatura
        if ($this->dup->length > 0) {
            $y = $this->pFaturaDANFE($x, $y+1);
        } else {
            //Se somente tiver a forma de pagamento sem pagamento ou outros não imprimir nada
            if (count($formaPag)=='1' && (isset($formaPag[90]) || isset($formaPag[99]))) {
                $y = $y;
            } else {
                //caso tenha mais de uma forma de pagamento ou seja diferente de boleto exibe a
                //forma de pagamento e o valor
                $y = $this->pagamentoDANFE($x, $y+1);
            }
        }
        //coloca os dados dos impostos e totais da NFe
        $y = $this->pImpostoDANFE($x, $y+1);
        //coloca os dados do trasnporte
        $y = $this->pTransporteDANFE($x, $y+1);
        //itens da DANFE
        $nInicial = 0;

        $y = $this->pItensDANFE($x, $y+1, $nInicial, $hDispo1, $pag, $totPag, $hCabecItens);

        //coloca os dados do ISSQN
        if ($linhaISSQN == 1) {
            $y = $this->pIssqnDANFE($x, $y+4);
        } else {
            $y += 4;
        }
        //coloca os dados adicionais da NFe
        $y = $this->pDadosAdicionaisDANFE($x, $y, $hdadosadic);
        //coloca o rodapé da página
        if ($this->orientacao == 'P') {
            $this->pRodape($xInic, $y-1);
        } else {
            $this->pRodape($xInic, $this->hPrint + 1);
        }

        //loop para páginas seguintes
        for ($n = 2; $n <= $totPagItens; $n++) {
            // fixa as margens
            $this->pdf->setMargins($margEsq, $margSup);
            //adiciona nova página
            $this->pdf->addPage($this->orientacao, $this->papel);
            //ajusta espessura das linhas
            $this->pdf->setLineWidth(0.1);
            //seta a cor do texto para petro
            $this->pdf->setTextColor(0, 0, 0);
            // posição inicial do relatorio
            $x = $xInic;
            $y = $yInic;
            //coloca o cabeçalho na página adicional
            $y = $this->pCabecalhoDANFE($x, $y, $n, $totPag);
            //coloca os itens na página adicional
            $y = $this->pItensDANFE($x, $y+1, $nInicial, $hDispo2, $n, $totPag, $hCabecItens);
            //coloca o rodapé da página
            if ($this->orientacao == 'P') {
                $this->pRodape($xInic, $y + 4);
            } else {
                $this->pRodape($xInic, $this->hPrint + 4);
            }
            //se estiver na última página e ainda restar itens para inserir, adiciona mais uma página
            if ($n == $totPagItens && $this->qtdeItensProc < $qtdeItens) {
                $totPagItens++;
                $totPag++;
            }
        }
        // PATCH 18: continuação dos dados adicionais
        $resto = $this->textoAdicContinuacao;
        for ($n = $totPagItens + 1; $resto !== '' && $n <= $totPag + 50; $n++) {
            $this->pdf->setMargins($margEsq, $margSup);
            $this->pdf->addPage($this->orientacao, $this->papel);
            $this->pdf->setLineWidth(0.1);
            $this->pdf->setTextColor(0, 0, 0);
            $y = $this->pCabecalhoDANFE($xInic, $yInic, $n, $totPag);
            $hBox = $this->hPrint + $yInic - $y - $hfooter - 4;
            $resto = $this->pDadosAdicionaisContinuacao($xInic, $y + 1, $this->wPrint, $hBox, $resto);
            if ($this->orientacao == 'P') {
                $this->pRodape($xInic, $y + 1 + $hBox + 3);
            } else {
                $this->pRodape($xInic, $this->hPrint + 4);
            }
        }
        //retorna o ID na NFe
        if ($classPdf!==false) {
            $aR = array(
             'id'=>str_replace('NFe', '', $this->infNFe->getAttribute("Id")),
             'classe_PDF'=>$this->pdf);
            return $aR;
        } else {
            return str_replace('NFe', '', $this->infNFe->getAttribute("Id"));
        }
    }//fim da função montaDANFE

    /**
     * anfavea
     * Função para transformar o campo cdata do padrão ANFAVEA para
     * texto imprimível
     *
     * @param  string $cdata campo CDATA
     * @return string conteúdo do campo CDATA como string
     */
    protected function pAnfavea($cdata = '')
    {
        if ($cdata == '') {
            return '';
        }
        // PATCH 11: só é Anfavea se tiver as tags dela com atributos. Antes, qualquer
        // "<" no texto (HTML colado pelo ERP, "PRECO < 10", "<<OFERTA>>") fazia
        // descartar tudo do primeiro "<" ao último ">": a informação sumia do DANFE.
        // Fora do formato Anfavea, o texto vai literal, exatamente como veio.
        if (!preg_match('/<\s*(id|div|entg|dest|ctl|ref)\b[^>]*=/i', $cdata)) {
            return $cdata;
        }
        //remove qualquer texto antes ou depois da tag CDATA
        $cdata = str_replace('<![CDATA[', '<CDATA>', $cdata);
        $cdata = str_replace(']]>', '</CDATA>', $cdata);
        $cdata = preg_replace('/\s\s+/', ' ', $cdata);
        $cdata = str_replace("> <", "><", $cdata);
        $len = strlen($cdata);
        $startPos = strpos($cdata, '<');
        if ($startPos === false) {
            return $cdata;
        }
        for ($x=$len; $x>0; $x--) {
            if (substr($cdata, $x, 1) == '>') {
                $endPos = $x;
                break;
            }
        }
        if ($startPos > 0) {
            $parte1 = substr($cdata, 0, $startPos);
        } else {
            $parte1 = '';
        }
        $parte2 = substr($cdata, $startPos, $endPos-$startPos+1);
        if ($endPos < $len) {
            $parte3 = substr($cdata, $endPos + 1, $len - $endPos - 1);
        } else {
            $parte3 = '';
        }
        $texto = trim($parte1).' '.trim($parte3);
        if (strpos($parte2, '<CDATA>') === false) {
            $cdata = '<CDATA>'.$parte2.'</CDATA>';
        } else {
            $cdata = $parte2;
        }
        //Retira a tag <FONTE IBPT> (caso existir) pois não é uma estrutura válida XML
        $cdata = str_replace('<FONTE IBPT>', '', $cdata);
        //carrega o xml CDATA em um objeto DOM
        $dom = new Dom();
        $dom->loadXML($cdata, LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG);
        //$xml = $dom->saveXML();
        //grupo CDATA infADprod
        $id = $dom->getElementsByTagName('id')->item(0);
        $div = $dom->getElementsByTagName('div')->item(0);
        $entg = $dom->getElementsByTagName('entg')->item(0);
        $dest = $dom->getElementsByTagName('dest')->item(0);
        $ctl = $dom->getElementsByTagName('ctl')->item(0);
        $ref = $dom->getElementsByTagName('ref')->item(0);
        if (isset($id)) {
            if ($id->hasAttributes()) {
                foreach ($id->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($div)) {
            if ($div->hasAttributes()) {
                foreach ($div->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($entg)) {
            if ($entg->hasAttributes()) {
                foreach ($entg->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($dest)) {
            if ($dest->hasAttributes()) {
                foreach ($dest->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($ctl)) {
            if ($ctl->hasAttributes()) {
                foreach ($ctl->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($ref)) {
            if ($ref->hasAttributes()) {
                foreach ($ref->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        //grupo CADATA infCpl
        $t = $dom->getElementsByTagName('transmissor')->item(0);
        $r = $dom->getElementsByTagName('receptor')->item(0);
        $versao = ! empty($dom->getElementsByTagName('versao')->item(0)->nodeValue) ?
            'Versao:'.$dom->getElementsByTagName('versao')->item(0)->nodeValue.' ' : '';
        $especieNF = ! empty($dom->getElementsByTagName('especieNF')->item(0)->nodeValue) ?
            'Especie:'.$dom->getElementsByTagName('especieNF')->item(0)->nodeValue.' ' : '';
        $fabEntrega = ! empty($dom->getElementsByTagName('fabEntrega')->item(0)->nodeValue) ?
            'Entrega:'.$dom->getElementsByTagName('fabEntrega')->item(0)->nodeValue.' ' : '';
        $dca = ! empty($dom->getElementsByTagName('dca')->item(0)->nodeValue) ?
            'dca:'.$dom->getElementsByTagName('dca')->item(0)->nodeValue.' ' : '';
        $texto .= "".$versao.$especieNF.$fabEntrega.$dca;
        if (isset($t)) {
            if ($t->hasAttributes()) {
                $texto .= " Transmissor ";
                foreach ($t->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        if (isset($r)) {
            if ($r->hasAttributes()) {
                $texto .= " Receptor ";
                foreach ($r->attributes as $attr) {
                    $name = $attr->nodeName;
                    $value = $attr->nodeValue;
                    $texto .= " $name : $value";
                }
            }
        }
        return $texto;
    }

    /**
     * Dados brutos do PDF
     * @return string
     */
    public function render()
    {
        return $this->pdf->getPdf();
    }

    protected function pNotaCancelada()
    {
        // PATCH 21: a situacao informada vale tambem sem nfeProc (antes era ignorada).
        if ($this->situacao_externa == self::SIT_CANCELADA) {
            return true;
        }
        if (!isset($this->nfeProc)) {
            return false;
        }
        $cStat = $this->getTagValue($this->nfeProc, "cStat");
        return $cStat == '101' ||
                $cStat == '151' ||
                $cStat == '135' ||
                $cStat == '155';
    }

    protected function pNotaDPEC()
    {
        return $this->situacao_externa == self::SIT_DPEC && $this->numero_registro_dpec != '';
    }

    protected function pNotaDenegada()
    {
        // PATCH 21: idem; e o 303 (destinatario nao habilitado) tambem e denegada.
        if ($this->situacao_externa == self::SIT_DENEGADA) {
            return true;
        }
        if (!isset($this->nfeProc)) {
            return false;
        }
        //NÃO ERA NECESSÁRIO ESSA FUNÇÃO POIS SÓ SE USA
        //1 VEZ NO ARQUIVO INTEIRO
        $cStat = $this->getTagValue($this->nfeProc, "cStat");
        return $cStat == '110' ||
               $cStat == '301' ||
               $cStat == '302' ||
               $cStat == '303';
    }

    /**
     *cabecalhoDANFE
     * Monta o cabelhalho da DANFE (retrato e paisagem)
     *
     * @param  number $x      Posição horizontal inicial, canto esquerdo
     * @param  number $y      Posição vertical inicial, canto superior
     * @param  number $pag    Número da Página
     * @param  number $totPag Total de páginas
     * @return number Posição vertical final
     */
    protected function pCabecalhoDANFE($x = 0, $y = 0, $pag = '1', $totPag = '1')
    {
        $oldX = $x;
        $oldY = $y;
        if ($this->orientacao == 'P') {
              $maxW = $this->wPrint;
        } else {
            if ($pag == 1) { // primeira página
                $maxW = $this->wPrint - $this->wCanhoto;
            } else { // páginas seguintes
                $maxW = $this->wPrint;
            }
        }
        //####################################################################################
        //coluna esquerda identificação do emitente
        $w = round($maxW*0.41, 0);
        if ($this->orientacao == 'P') {
            $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'I');
        } else {
            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'B');
        }
        $w1 = $w;
        $h=32;
        $oldY += $h;
        $this->pTextBox($x, $y, $w, $h);
        $texto = 'IDENTIFICAÇÃO DO EMITENTE';
        $this->pTextBox($x, $y, $w, 5, $texto, $aFont, 'T', 'C', 0, '');
        //estabelecer o alinhamento
        //pode ser left L, center C, right R, full logo L
        //se for left separar 1/3 da largura para o tamanho da imagem
        //os outros 2/3 serão usados para os dados do emitente
        //se for center separar 1/2 da altura para o logo e 1/2 para os dados
        //se for right separa 2/3 para os dados e o terço seguinte para o logo
        //se não houver logo centraliza dos dados do emitente
        // coloca o logo
        if (!empty($this->logomarca)) {
            $logoInfo = getimagesize($this->logomarca);
            $type = strtolower(explode('/', $logoInfo['mime'])[1]);
            if ($type == 'png') {
                $this->logomarca = $this->imagePNGtoJPG($this->logomarca);
                $type == 'jpg';
            }
            //largura da imagem em mm
            $logoWmm = ($logoInfo[0]/72)*25.4;
            //altura da imagem em mm
            $logoHmm = ($logoInfo[1]/72)*25.4;
            if ($this->logoAlign=='L') {
                $nImgW = round($w/3, 0);
                $nImgH = round($logoHmm * ($nImgW/$logoWmm), 0);
                $xImg = $x+1;
                $yImg = round(($h-$nImgH)/2, 0)+$y;
                //estabelecer posições do texto
                $x1 = round($xImg + $nImgW +1, 0);
                $y1 = round($h/3+$y, 0);
                $tw = round(2*$w/3, 0);
            } elseif ($this->logoAlign=='C') {
                $nImgH = round($h/3, 0);
                $nImgW = round($logoWmm * ($nImgH/$logoHmm), 0);
                $xImg = round(($w-$nImgW)/2+$x, 0);
                $yImg = $y+3;
                $x1 = $x;
                $y1 = round($yImg + $nImgH + 1, 0);
                $tw = $w;
            } elseif ($this->logoAlign=='R') {
                $nImgW = round($w/3, 0);
                $nImgH = round($logoHmm * ($nImgW/$logoWmm), 0);
                $xImg = round($x+($w-(1+$nImgW)), 0);
                $yImg = round(($h-$nImgH)/2, 0)+$y;
                $x1 = $x;
                $y1 = round($h/3+$y, 0);
                $tw = round(2*$w/3, 0);
            } elseif ($this->logoAlign=='F') {
                $nImgH = round($h-5, 0);
                $nImgW = round($logoWmm * ($nImgH/$logoHmm), 0);
                $xImg = round(($w-$nImgW)/2+$x, 0);
                $yImg = $y+3;
                $x1 = $x;
                $y1 = round($yImg + $nImgH + 1, 0);
                $tw = $w;
            }
            $type = (substr($this->logomarca, 0, 7) === 'data://') ? 'jpg' : null;
            $this->pdf->Image($this->logomarca, $xImg, $yImg, $nImgW, $nImgH, $type);
        } else {
            $x1 = $x;
            $y1 = round($h/3+$y, 0);
            $tw = $w;
        }
        // monta as informações apenas se diferente de full logo
        if ($this->logoAlign !== 'F') {
            //Nome emitente
            $aFont = array('font'=>$this->fontePadrao, 'size'=>12, 'style'=>'B');
            $texto = $this->emit->getElementsByTagName("xNome")->item(0)->nodeValue;
            $this->pTextBox($x1, $y1, $tw, 8, $texto, $aFont, 'T', 'C', 0, '');
            //endereço
            $y1 = $y1+5;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
            // PATCH 20: sem enderEmit (XML de teste) não derruba o DANFE; endereço em branco
            $fone = ! empty($this->enderEmit) && ! empty($this->enderEmit->getElementsByTagName("fone")->item(0)->nodeValue)
                 ? $this->enderEmit->getElementsByTagName("fone")->item(0)->nodeValue
                 : '';
            $lgr = $this->getTagValue($this->enderEmit, "xLgr");
            $nro = $this->getTagValue($this->enderEmit, "nro");
            $cpl = $this->getTagValue($this->enderEmit, "xCpl", " - ");
            $bairro = $this->getTagValue($this->enderEmit, "xBairro");
            $CEP = $this->getTagValue($this->enderEmit, "CEP");
            $CEP = $this->pFormat($CEP, "#####-###");
            $mun = $this->getTagValue($this->enderEmit, "xMun");
            $UF = $this->getTagValue($this->enderEmit, "UF");
            $texto = $lgr . ", " . $nro . $cpl . "\n" . $bairro . " - "
                    . $CEP . "\n" . $mun . " - " . $UF . " "
                    . "Fone/Fax: " . $fone;
            if (empty($this->enderEmit)) {
                $texto = '';
            }
            // PATCH 10: linha mais larga que a caixa era reduzida até virar
            // microtexto; agora quebra em mais linhas (o quadro tem altura) com a
            // maior fonte que couber. Se as 3 linhas cabem, nada muda.
            if ($this->pGetNumLines($texto, $tw - 1, $aFont) <= 3) {
                $this->pTextBox($x1, $y1, $tw, 8, $texto, $aFont, 'T', 'C', 0, '');
            } else {
                $hDisp = $y + $h - $y1 - 1;
                $aFont = $this->fonteQueCabe($texto, $tw - 1, $hDisp, 8, 5.5);
                $this->pTextBox($x1 + 0.5, $y1, $tw - 1, $hDisp, $texto, $aFont, 'T', 'C', 0, '', false);
            }
        }

        //####################################################################################
        //coluna central Danfe
        $x += $w;
        $w=round($maxW * 0.17, 0);//35;
        $w2 = $w;
        $h = 32;
        $this->pTextBox($x, $y, $w, $h);

        if (! $this->pNotaCancelada()) {
            // A PRINCIPIO NÃO PRECISAVA, POIS A NFE ESTÁ AUTORIZADA,
            // SÓ SE RETIRA O DANFE PARA NOTAS NÃO AUTORIZADAS
            $texto = "DANFE";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>14, 'style'=>'B');
            $this->pTextBox($x, $y+1, $w, $h, $texto, $aFont, 'T', 'C', 0, '');
            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
            $texto = 'Documento Auxiliar da Nota Fiscal Eletrônica';
            $h = 20;
            $this->pTextBox($x, $y+6, $w, $h, $texto, $aFont, 'T', 'C', 0, '', false);
        }

        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $texto = '0 - ENTRADA';
        $y1 = $y + 14;
        $h = 8;
        $this->pTextBox($x+2, $y1, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $texto = '1 - SAÍDA';
        $y1 = $y + 17;
        $this->pTextBox($x+2, $y1, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        //tipo de nF
        $aFont = array('font'=>$this->fontePadrao, 'size'=>12, 'style'=>'B');
        $y1 = $y + 13;
        $h = 7;
        $texto = $this->ide->getElementsByTagName('tpNF')->item(0)->nodeValue;
        $this->pTextBox($x+27, $y1, 5, $h, $texto, $aFont, 'C', 'C', 1, '');
        //numero da NF
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $y1 = $y + 20;
        $numNF = str_pad($this->ide->getElementsByTagName('nNF')->item(0)->nodeValue, 9, "0", STR_PAD_LEFT);
        $numNF = $this->pFormat($numNF, "###.###.###");
        $texto = "Nº. " . $numNF;
        $this->pTextBox($x, $y1, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
        //Série
        $y1 = $y + 23;
        $serie = str_pad($this->ide->getElementsByTagName('serie')->item(0)->nodeValue, 3, "0", STR_PAD_LEFT);
        $texto = "Série " . $serie;
        $this->pTextBox($x, $y1, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
        //numero paginas
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'I');
        $y1 = $y + 26;
        $texto = "Folha " . $pag . "/" . $totPag;
        $this->pTextBox($x, $y1, $w, $h, $texto, $aFont, 'C', 'C', 0, '');

        //####################################################################################
        //coluna codigo de barras
        $x += $w;
        $w = ($maxW-$w1-$w2);//85;
        $w3 = $w;
        $h = 32;
        $this->pTextBox($x, $y, $w, $h);
        $this->pdf->SetFillColor(0, 0, 0);
        $chave_acesso = str_replace('NFe', '', $this->infNFe->getAttribute("Id"));
        $bW = 75;
        $bH = 12;
        //codigo de barras
        $this->pdf->Code128($x+(($w-$bW)/2), $y+2, $chave_acesso, $bW, $bH);
        //linhas divisorias
        $this->pdf->Line($x, $y+4+$bH, $x+$w, $y+4+$bH);
        $this->pdf->Line($x, $y+12+$bH, $x+$w, $y+12+$bH);
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $y1 = $y+4+$bH;
        $h = 7;
        $texto = 'CHAVE DE ACESSO';
        $this->pTextBox($x, $y1, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'B');
        $y1 = $y+8+$bH;
        $texto = $this->pFormat($chave_acesso, $this->formatoChave);
        $this->pTextBox($x+2, $y1, $w-2, $h, $texto, $aFont, 'T', 'C', 0, '');
        $y1 = $y+12+$bH;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
        $chaveContingencia="";
        if ($this->pNotaDPEC()) {
            $cabecalhoProtoAutorizacao = 'NÚMERO DE REGISTRO DPEC';
        } else {
            $cabecalhoProtoAutorizacao = 'PROTOCOLO DE AUTORIZAÇÃO DE USO';
        }
        if (($this->tpEmis == 2 || $this->tpEmis == 5) && !$this->pNotaDPEC()) {
            $cabecalhoProtoAutorizacao = "DADOS DA NF-E";
            $chaveContingencia = $this->pGeraChaveAdicionalDeContingencia();
            $this->pdf->SetFillColor(0, 0, 0);
            //codigo de barras
            $this->pdf->Code128($x+11, $y1+1, $chaveContingencia, $bW*.9, $bH/2);
        } else {
            $texto = 'Consulta de autenticidade no portal nacional da NF-e';
            $this->pTextBox($x+2, $y1, $w-2, $h, $texto, $aFont, 'T', 'C', 0, '');
            $y1 = $y+16+$bH;
            $texto = 'www.nfe.fazenda.gov.br/portal ou no site da Sefaz Autorizadora';
            $this->pTextBox(
                $x+2,
                $y1,
                $w-2,
                $h,
                $texto,
                $aFont,
                'T',
                'C',
                0,
                'http://www.nfe.fazenda.gov.br/portal ou no site da Sefaz Autorizadora'
            );
        }

        //####################################################################################
        //Dados da NF do cabeçalho
        //natureza da operação
        $texto = 'NATUREZA DA OPERAÇÃO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $w = $w1+$w2;
        $y = $oldY;
        $oldY += $h;
        $x = $oldX;
        $h = 7;
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = $this->ide->getElementsByTagName("natOp")->item(0)->nodeValue;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        $x += $w;
        $w = $w3;
        //PROTOCOLO DE AUTORIZAÇÃO DE USO ou DADOS da NF-E
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $cabecalhoProtoAutorizacao, $aFont, 'T', 'L', 1, '');
        // algumas NFe podem estar sem o protocolo de uso portanto sua existencia deve ser
        // testada antes de tentar obter a informação.
        // NOTA : DANFE sem protocolo deve existir somente no caso de contingência !!!
        // Além disso, existem várias NFes em contingência que eu recebo com protocolo de autorização.
        // Na minha opinião, deveríamos mostra-lo, mas o  manual  da NFe v4.01 diz outra coisa...
        if (($this->tpEmis == 2 || $this->tpEmis == 5) && !$this->pNotaDPEC()) {
            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'B');
            $texto = $this->pFormat($chaveContingencia, "#### #### #### #### #### #### #### #### ####");
            $cStat = '';
        } else {
            $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
            if ($this->pNotaDPEC()) {
                $texto = $this->numero_registro_dpec;
                $cStat = '';
            } else {
                if (isset($this->nfeProc)) {
                    $texto = ! empty($this->nfeProc->getElementsByTagName("nProt")->item(0)->nodeValue) ?
                            $this->nfeProc->getElementsByTagName("nProt")->item(0)->nodeValue : '';
                    $tsHora = $this->pConvertTime($this->getTagValue($this->nfeProc, "dhRecbto"));
                    if ($texto != '') {
                        $texto .= "  -  " . date('d/m/Y H:i:s', $tsHora);
                    }
                    $cStat = $this->getTagValue($this->nfeProc, "cStat");
                } else {
                    $texto = '';
                    $cStat = '';
                }
            }
        }
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        //####################################################################################
        //INSCRIÇÃO ESTADUAL
        $w = round($maxW * 0.333, 0);
        $y += $h;
        $oldY += $h;
        $x = $oldX;
        $texto = 'INSCRIÇÃO ESTADUAL';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = $this->getTagValue($this->emit, "IE");
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        //INSCRIÇÃO ESTADUAL DO SUBST. TRIBUT.
        $x += $w;
        $texto = 'INSCRIÇÃO ESTADUAL DO SUBST. TRIBUT.';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = ! empty($this->emit->getElementsByTagName("IEST")->item(0)->nodeValue)
             ? $this->emit->getElementsByTagName("IEST")->item(0)->nodeValue
             : '';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        //CNPJ
        $x += $w;
        $w = ($maxW-(2*$w));
        $texto = 'CNPJ / CPF';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        //Pegando valor do CPF/CNPJ
        if (! empty($this->emit->getElementsByTagName("CNPJ")->item(0)->nodeValue)) {
            $texto = $this->pFormat(
                $this->emit->getElementsByTagName("CNPJ")->item(0)->nodeValue,
                "###.###.###/####-##"
            );
        } else {
            $texto = ! empty($this->emit->getElementsByTagName("CPF")->item(0)->nodeValue) ?
                $this->pFormat(
                    $this->emit->getElementsByTagName("CPF")->item(0)->nodeValue,
                    "###.###.###-##"
                ) : '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');

        //####################################################################################
        //Indicação de NF Homologação, cancelamento e falta de protocolo
        $tpAmb = $this->ide->getElementsByTagName('tpAmb')->item(0)->nodeValue;
        //indicar cancelamento
        if ($this->pNotaCancelada()) {
            //101 Cancelamento
            $x = 10;
            $y = $this->hPrint-130;
            $h = 25;
            $w = $maxW-(2*$x);
            $this->pdf->SetTextColor(90, 90, 90);
            $texto = "NFe CANCELADA";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(0, 0, 0);
        }

        if ($this->pNotaDPEC() || $this->tpEmis == 4) {
            //DPEC
            $x = 10;
            $y = $this->hPrint-130;
            $h = 25;
            $w = $maxW-(2*$x);
            $this->pdf->SetTextColor(200, 200, 200);
            $texto = "DANFE impresso em contingência -\n".
                     "DPEC regularmente recebido pela Receita\n".
                     "Federal do Brasil";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(0, 0, 0);
        }
        if ($this->pNotaDenegada()) {
            //110 301 302 Denegada
            $x = 10;
            $y = $this->hPrint-130;
            $h = 25;
            $w = $maxW-(2*$x);
            $this->pdf->SetTextColor(90, 90, 90);
            $texto = "NFe USO DENEGADO";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $y += $h;
            $h = 5;
            $w = $maxW-(2*$x);
            if (isset($this->infProt)) {
                $xMotivo = $this->infProt->getElementsByTagName("xMotivo")->item(0)->nodeValue;
            } else {
                $xMotivo = '';
            }
            $texto = "SEM VALOR FISCAL\n".$xMotivo;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(0, 0, 0);
        }
        //indicar sem valor
        if ($tpAmb != 1) {
            $x = 10;
            if ($this->orientacao == 'P') {
                $y = round($this->hPrint*2/3, 0);
            } else {
                $y = round($this->hPrint/2, 0);
            }
            $h = 5;
            $w = $maxW-(2*$x);
            $this->pdf->SetTextColor(90, 90, 90);
            $texto = "SEM VALOR FISCAL";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $aFont = array('font'=>$this->fontePadrao, 'size'=>30, 'style'=>'B');
            $texto = "AMBIENTE DE HOMOLOGAÇÃO";
            $this->pTextBox($x, $y+14, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            $this->pdf->SetTextColor(0, 0, 0);
        } else {
            $x = 10;
            if ($this->orientacao == 'P') {
                $y = round($this->hPrint*2/3, 0);
            } else {
                $y = round($this->hPrint/2, 0);
            }//fim orientacao
            $h = 5;
            $w = $maxW-(2*$x);
            $this->pdf->SetTextColor(90, 90, 90);
            //indicar FALTA DO PROTOCOLO se NFe não for em contingência
            if (($this->tpEmis == 2 || $this->tpEmis == 5) && !$this->pNotaDPEC()) {
                //Contingência
                $texto = "DANFE Emitido em Contingência";
                $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
                $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                $aFont = array('font'=>$this->fontePadrao, 'size'=>30, 'style'=>'B');
                $texto = "devido à problemas técnicos";
                $this->pTextBox($x, $y+12, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
            } else {
                if (!isset($this->nfeProc)) {
                    if (!$this->pNotaDPEC()) {
                        $texto = "SEM VALOR FISCAL";
                        $aFont = array('font'=>$this->fontePadrao, 'size'=>48, 'style'=>'B');
                        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                    }
                    $aFont = array('font'=>$this->fontePadrao, 'size'=>30, 'style'=>'B');
                    $texto = "FALTA PROTOCOLO DE APROVAÇÃO DA SEFAZ";
                    if (!$this->pNotaDPEC()) {
                        $this->pTextBox($x, $y+12, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                    } else {
                        $this->pTextBox($x, $y+25, $w, $h, $texto, $aFont, 'C', 'C', 0, '');
                    }
                }//fim nefProc
            }//fim tpEmis
            $this->pdf->SetTextColor(0, 0, 0);
        }
        return $oldY;
    } //fim cabecalhoDANFE

    /**
     * destinatarioDANFE
     * Monta o campo com os dados do destinatário na DANFE. (retrato e paisagem)
     *
     * @name   destinatarioDANFE
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function pDestinatarioDANFE($x = 0, $y = 0)
    {
        // PATCH 20: <dest> é opcional no schema; sem ele o quadro sai com os campos em
        // branco (elemento vazio no lugar), em vez de "getElementsByTagName() on null"
        $dest = empty($this->dest) ? $this->dom->createElement('dest') : $this->dest;
        //####################################################################################
        //DESTINATÁRIO / REMETENTE
        $oldX = $x;
        $oldY = $y;
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            $maxW = $this->wPrint - $this->wCanhoto;
        }
        $w = $maxW;
        $h = 7;
        $texto = 'DESTINATÁRIO / REMETENTE';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        //NOME / RAZÃO SOCIAL
        $w = round($maxW*0.61, 0);
        $w1 = $w;
        $y += 3;
        $texto = 'NOME / RAZÃO SOCIAL';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = $dest->getElementsByTagName("xNome")->item(0)->nodeValue;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        if ($this->orientacao == 'P') {
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'L', 0, '');
        } else {
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'L', 1, '');
        }
        //CNPJ / CPF
        $x += $w;
        $w = round($maxW*0.23, 0);
        $w2 = $w;
        $texto = 'CNPJ / CPF';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        //Pegando valor do CPF/CNPJ
        if (! empty($dest->getElementsByTagName("CNPJ")->item(0)->nodeValue)) {
            $texto = $this->pFormat(
                $dest->getElementsByTagName("CNPJ")->item(0)->nodeValue,
                "###.###.###/####-##"
            );
        } else {
            $texto = ! empty($dest->getElementsByTagName("CPF")->item(0)->nodeValue) ?
                    $this->pFormat(
                        $dest->getElementsByTagName("CPF")->item(0)->nodeValue,
                        "###.###.###-##"
                    ) : '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        //DATA DA EMISSÃO
        $x += $w;
        $w = $maxW-($w1+$w2);
        $wx = $w;
        $texto = 'DATA DA EMISSÃO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $dEmi = ! empty($this->ide->getElementsByTagName("dEmi")->item(0)->nodeValue) ?
                $this->ide->getElementsByTagName("dEmi")->item(0)->nodeValue : '';
        if ($dEmi == '') {
            $dEmi = ! empty($this->ide->getElementsByTagName("dhEmi")->item(0)->nodeValue) ?
                    $this->ide->getElementsByTagName("dhEmi")->item(0)->nodeValue : '';
            $aDemi = explode('T', $dEmi);
            $dEmi = $aDemi[0];
        }
        $texto = $this->pYmd2dmy($dEmi);
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        if ($this->orientacao == 'P') {
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        } else {
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 1, '');
        }
        //ENDEREÇO
        $w = round($maxW*0.47, 0);
        $w1 = $w;
        $y += $h;
        $x = $oldX;
        $texto = 'ENDEREÇO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = $dest->getElementsByTagName("xLgr")->item(0)->nodeValue;
        $texto .= ', ' . $dest->getElementsByTagName("nro")->item(0)->nodeValue;
        $texto .= $this->getTagValue($dest, "xCpl", " - ");

        $this->valorQueCabe($x, $y, $w, $h, $texto, 10, 'B', 'B', 'L'); // PATCH 10
        //BAIRRO / DISTRITO
        $x += $w;
        $w = round($maxW*0.21, 0);
        $w2 = $w;
        $texto = 'BAIRRO / DISTRITO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = $dest->getElementsByTagName("xBairro")->item(0)->nodeValue;
        $this->valorQueCabe($x, $y, $w, $h, $texto, 10, 'B', 'B', 'C'); // PATCH 10
        //CEP
        $x += $w;
        $w = $maxW-$w1-$w2-$wx;
        $w2 = $w;
        $texto = 'CEP';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = ! empty($dest->getElementsByTagName("CEP")->item(0)->nodeValue) ?
                $dest->getElementsByTagName("CEP")->item(0)->nodeValue : '';
        $texto = $this->pFormat($texto, "#####-###");
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        //DATA DA SAÍDA
        $x += $w;
        $w = $wx;
        $texto = 'DATA DA SAÍDA/ENTRADA';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $dSaiEnt = ! empty($this->ide->getElementsByTagName("dSaiEnt")->item(0)->nodeValue) ?
                $this->ide->getElementsByTagName("dSaiEnt")->item(0)->nodeValue : '';
        if ($dSaiEnt == '') {
            $dSaiEnt = ! empty($this->ide->getElementsByTagName("dhSaiEnt")->item(0)->nodeValue) ?
                    $this->ide->getElementsByTagName("dhSaiEnt")->item(0)->nodeValue : '';
            $aDsaient = explode('T', $dSaiEnt);
            $dSaiEnt = $aDsaient[0];
        }
        $texto = $this->pYmd2dmy($dSaiEnt);
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        //MUNICÍPIO
        $w = $w1;
        $y += $h;
        $x = $oldX;
        $texto = 'MUNICÍPIO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = $dest->getElementsByTagName("xMun")->item(0)->nodeValue;
        if (strtoupper(trim($texto)) == "EXTERIOR" && $dest->getElementsByTagName("xPais")->length > 0) {
            $texto .= " - " .  $dest->getElementsByTagName("xPais")->item(0)->nodeValue;
        }
        $this->valorQueCabe($x, $y, $w, $h, $texto, 10, 'B', 'B', 'L'); // PATCH 10
        //UF
        $x += $w;
        $w = 8;
        $texto = 'UF';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = $dest->getElementsByTagName("UF")->item(0)->nodeValue;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        //FONE / FAX
        $x += $w;
        $w = round(($maxW -$w1-$wx-8)/2, 0);
        $w3 = $w;
        $texto = 'FONE / FAX';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = ! empty($dest->getElementsByTagName("fone")->item(0)->nodeValue) ?
                $dest->getElementsByTagName("fone")->item(0)->nodeValue : '';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        //INSCRIÇÃO ESTADUAL
        $x += $w;
        $w = $maxW -$w1-$wx-8-$w3;
        $texto = 'INSCRIÇÃO ESTADUAL';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $IE = $dest->getElementsByTagName("IE");
        $texto = ($IE && $IE->length > 0) ? $IE->item(0)->nodeValue : '';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        //HORA DA SAÍDA
        $x += $w;
        $w = $wx;
        $texto = 'HORA DA SAÍDA/ENTRADA';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $hSaiEnt = ! empty($this->ide->getElementsByTagName("hSaiEnt")->item(0)->nodeValue) ?
                $this->ide->getElementsByTagName("hSaiEnt")->item(0)->nodeValue : '';
        if ($hSaiEnt == '') {
            $dhSaiEnt = ! empty($this->ide->getElementsByTagName("dhSaiEnt")->item(0)->nodeValue) ?
                    $this->ide->getElementsByTagName("dhSaiEnt")->item(0)->nodeValue : '';
            $tsDhSaiEnt = $this->pConvertTime($dhSaiEnt);
            if ($tsDhSaiEnt != '') {
                $hSaiEnt = date('H:i:s', $tsDhSaiEnt);
            }
        }
        $texto = $hSaiEnt;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'C', 0, '');
        return ($y + $h);
    } //fim da função destinatarioDANFE

     /**
     * pGetTextoFatura
     * Gera a String do Texto da Fatura
      *
     * @name   getTextoFatura
     * @return uma String com o texto ou "";
     */
    protected function pGetTextoFatura()
    {
        if (isset($this->cobr)) {
            $fat = $this->cobr->getElementsByTagName("fat")->item(0);
            if (isset($fat)) {
                if (!empty($this->getTagValue($this->ide, "indPag"))) {
                    $textoIndPag = "";
                    $indPag = $this->getTagValue($this->ide, "indPag");
                    if ($indPag === "0") {
                        $textoIndPag = "Pagamento à Vista - ";
                    } elseif ($indPag === "1") {
                        $textoIndPag = "Pagamento à Prazo - ";
                    }
                    $nFat = $this->getTagValue($fat, "nFat", "Fatura: ");
                    $vOrig = $this->getTagValue($fat, "vOrig", " Valor Original: ");
                    $vDesc = $this->getTagValue($fat, "vDesc", " Desconto: ");
                    $vLiq = $this->getTagValue($fat, "vLiq", " Valor Líquido: ");
                    $texto = $textoIndPag . $nFat . $vOrig . $vDesc . $vLiq;
                    return $texto;
                } else {
                    $pag = $this->dom->getElementsByTagName("pag");
                    if ($tPag = $this->getTagValue($pag->item(0), "tPag")) {
                        return $this->tipoPag($tPag);
                    }
                }
            }
        }
        return "";
    } //fim getTextoFatura

     /**
     * pSizeExtraTextoFatura
     * Calcula o espaço ocupado pelo texto da fatura. Este espaço só é utilizado quando não houver duplicata.
      *
     * @name   pSizeExtraTextoFatura
     * @return integer
     */
    protected function pSizeExtraTextoFatura()
    {
        $textoFatura = $this->pGetTextoFatura();
        //verificar se existem duplicatas
        if ($this->dup->length == 0 && $textoFatura !== "") {
            return 10;
        }
        return 0;
    }

    /**
     * faturaDANFE
     * Monta o campo de duplicatas da DANFE (retrato e paisagem)
     *
     * @name   faturaDANFE
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function pFaturaDANFE($x, $y)
    {
        $linha = 1;
        $h = 8+3;
        $oldx = $x;
        $textoFatura = $this->pGetTextoFatura();
        //verificar se existem duplicatas
        if ($this->dup->length > 0 || $textoFatura !== "") {
            //#####################################################################
            //FATURA / DUPLICATA
            $texto = "FATURA / DUPLICATA";
            if ($this->orientacao == 'P') {
                $w = $this->wPrint;
            } else {
                $w = 271;
            }
            $h = 8;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            $y += 3;
            $dups = "";
            $dupcont = 0;
            $nFat = $this->dup->length;
            if ($textoFatura !== "" && $this->exibirTextoFatura) {
                $myH=6;
                $myW = $this->wPrint;
                if ($this->orientacao == 'L') {
                    $myW -= $this->wCanhoto;
                }
                $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'');
                $this->pTextBox($x, $y, $myW, $myH, $textoFatura, $aFont, 'C', 'L', 1, '');
                $y+=$myH+1;
            }
            if ($this->orientacao == 'P') {
                $w = round($this->wPrint/7.018, 0)-1;
            } else {
                $w = 28;
            }
            $increm = 1;
            if (empty($this->dupGrade) && $this->dup->length > 0) {
                // PATCH 18: nenhuma duplicata cabe na célula: todas estão nos dados adicionais
                $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
                $this->pTextBox($x, $y, $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto, 6, 'DUPLICATAS RELACIONADAS EM DADOS ADICIONAIS', $aFont, 'C', 'L', 1, '');
                return $y + 6;
            }
            // PATCH 18: só as duplicatas que cabem no quadro (ver prepararDuplicatas())
            foreach ($this->dupGrade as $k) {
                $nDup = ! empty($this->dup->item($k)->getElementsByTagName('nDup')->item(0)->nodeValue) ?
                        $this->dup->item($k)->getElementsByTagName('nDup')->item(0)->nodeValue : '';
                $dDup = ! empty($this->dup->item($k)->getElementsByTagName('dVenc')->item(0)->nodeValue) ?
                        $this->pYmd2dmy($this->dup->item($k)->getElementsByTagName('dVenc')->item(0)->nodeValue) : '';
                $vDup = ! empty($this->dup->item($k)->getElementsByTagName('vDup')->item(0)->nodeValue) ?
                        'R$ ' . number_format((float)$this->dup->item($k)->getElementsByTagName('vDup')->item(0)->nodeValue,
                            2,
                            ",",
                            "."
                        ) : '';
                $h = 8;
                $texto = '';
                if ($nDup!='0' && $nDup!='') {
                    $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
                    $this->pTextBox($x, $y, $w, $h, 'Num.', $aFont, 'T', 'L', 1, '');
                    $aFont = array('font'=>$this->fontePadrao, 'size'=>$this->fonteValorDup($nDup, $w, 'Num.'), 'style'=>'B');
                    $this->pTextBox($x, $y, $w, $h, $nDup, $aFont, 'T', 'R', 0, '');
                } else {
                    $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
                    $this->pTextBox($x, $y, $w, $h, ($dupcont+1)."", $aFont, 'T', 'L', 1, '');
                }
                $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
                $this->pTextBox($x, $y, $w, $h, 'Venc.', $aFont, 'C', 'L', 0, '');
                $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
                $this->pTextBox($x, $y, $w, $h, $dDup, $aFont, 'C', 'R', 0, '');
                $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
                $this->pTextBox($x, $y, $w, $h, 'Valor', $aFont, 'B', 'L', 0, '');
                $aFont = array('font'=>$this->fontePadrao, 'size'=>$this->fonteValorDup($vDup, $w, 'Valor'), 'style'=>'B');
                $this->pTextBox($x, $y, $w, $h, $vDup, $aFont, 'B', 'R', 0, '');
                $x += $w+$increm;
                $dupcont += 1;
                if ($this->orientacao == 'P') {
                    $maxDupCont = 6;
                } else {
                    $maxDupCont = 8;
                }
                if ($dupcont > $maxDupCont) {
                    $y += 9;
                    $x = $oldx;
                    $dupcont = 0;
                    $linha += 1;
                }
                if ($linha == 5) {
                    $linha = 4;
                    break;
                }
            }
            if ($dupcont == 0) {
                $y -= 9;
                $linha--;
            }
            return ($y+$h);
        } else {
            $linha = 0;
            return ($y-2);
        }
    } //fim da função faturaDANFE

    /**
     * pagamentoDANFE
     * Monta o campo de pagamentos da DANFE (retrato e paisagem) (foi baseada na faturaDANFE)
     *
     * @name   pagamentoDANFE
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function pagamentoDANFE($x, $y)
    {
        $linha = 1;
        $h = 8+3;
        $oldx = $x;
        //verificar se existem cobranças definidas
        if (isset($this->detPag) && $this->detPag->length > 0) {
            //#####################################################################
            //Tipo de pagamento
            $texto = "PAGAMENTO";
            if ($this->orientacao == 'P') {
                $w = $this->wPrint;
            } else {
                $w = 271;
            }
            $h = 8;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
            $y += 3;
            $dups = "";
            $dupcont = 0;
            if ($this->orientacao == 'P') {
                $w = round($this->wPrint/7.018, 0)-1;
            } else {
                $w = 28;
            }
            if ($this->orientacao == 'P') {
                $maxDupCont = 6;
            } else {
                $maxDupCont = 8;
            }
            $increm = 1;
            $formaPagamento = array('01'=>'Dinheiro','02'=>'Cheque','03'=>'Cartão de Crédito',
                                    '04'=>'Cartão de Débito','05'=>'Crédito Loja','10'=>'Vale Alimentação',
                                    '11'=>'Vale Refeição','12'=>'Vale Presente','13'=>'Vale Combustível',
                                    '14'=>'Duplicata Mercantil','15'=>'Boleto','90'=>'Sem pagamento','99'=>'Outros');
            $bandeira = array('01'=>'Visa','02'=>'Mastercard','03'=>'American','04'=>'Sorocred','05'=>'Diners',
                              '06'=>'Elo','07'=>'Hipercard','08'=>'Aura','09'=>'Cabal','99'=>'Outros');
            foreach ($this->detPag as $k => $d) {
                $fPag = !empty($this->detPag->item($k)->getElementsByTagName('tPag')->item(0)->nodeValue)
                    ? $this->detPag->item($k)->getElementsByTagName('tPag')->item(0)->nodeValue : '0';
                $vPag = ! empty($this->detPag->item($k)->getElementsByTagName('vPag')->item(0)->nodeValue)
                    ? 'R$ ' . number_format((float)$this->detPag->item($k)->getElementsByTagName('vPag')->item(0)->nodeValue,
                        2,
                        ",",
                        "."
                    ) : '';
                $h = 6;
                $texto = '';
                if (isset($formaPagamento[$fPag])) {
                    /*Exibir Item sem pagamento ou outros?*/
                    if ($fPag=='90' || $fPag=='99') {
                        continue;
                    }
                    $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
                    $this->pTextBox($x, $y, $w, $h, 'Forma', $aFont, 'T', 'L', 1, '');
                    $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
                    $this->pTextBox($x, $y, $w, $h, $formaPagamento[$fPag], $aFont, 'T', 'R', 0, '');
                } else {
                    $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
                    $this->pTextBox($x, $y, $w, $h, "Forma ".$fPag." não encontrado", $aFont, 'T', 'L', 1, '');
                }
                $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
                $this->pTextBox($x, $y, $w, $h, 'Valor', $aFont, 'B', 'L', 0, '');
                $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
                $this->pTextBox($x, $y, $w, $h, $vPag, $aFont, 'B', 'R', 0, '');
                $x += $w+$increm;
                $dupcont += 1;

                if ($dupcont>$maxDupCont) {
                    $y += 9;
                    $x = $oldx;
                    $dupcont = 0;
                    $linha += 1;
                }
                if ($linha == 5) {
                    $linha = 4;
                    break;
                }
            }
            if ($dupcont == 0) {
                $y -= 9;
                $linha--;
            }
            return ($y+$h);
        } else {
            $linha = 0;
            return ($y-2);
        }
    } //fim da função pagamentoDANFE
    
    /**
     * impostoDanfeHelper
     * Auxilia a montagem dos campos de impostos e totais da DANFE
     *
     * @name   impostoDanfeHelper
     * @param  float $x Posição horizontal canto esquerdo
     * @param  float $y Posição vertical canto superior
     * @param  float $w Largura do campo
     * @param  float $h Altura do campo
     * @param  float $h Título do campo
     * @param  float $h Valor do imposto
     * @return float Sugestão do $x do próximo imposto
     */
    protected function pImpostoDanfeHelper($x, $y, $w, $h, $titulo, $campoImposto)
    {
        // PATCH 20: sem o grupo ICMSTot os totais ficam em branco (nada inventado);
        // com o grupo, tag ausente continua "0, 00" como no original
        $valorImposto = empty($this->ICMSTot) ? '' : '0, 00';
        $the_field = empty($this->ICMSTot) ? null : $this->ICMSTot->getElementsByTagName($campoImposto)->item(0);
        if (isset($the_field)) {
            $the_value = $the_field->nodeValue;
            if (!empty($the_value)) {
                $valorImposto = number_format((float)$the_value, 2, ",", ".");
            }
        }

        $fontTitulo = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $fontValor = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $titulo, $fontTitulo, 'T', 'L', 1, '');
        $this->pTextBox($x, $y, $w, $h, $valorImposto, $fontValor, 'B', 'R', 0, '');

        $next_x = $x + $w;
        return $next_x;
    }

    /**
     * impostoDANFE
     * Monta o campo de impostos e totais da DANFE (retrato e paisagem)
     *
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     */
    protected function pImpostoDANFE($x, $y)
    {
        $x_inicial = $x;
        //#####################################################################


        $campos_por_linha = 9;
        if (!$this->exibirPIS) {
            $campos_por_linha--;
        }
        if (!$this->exibirIcmsInterestadual) {
            $campos_por_linha -= 2;
        }

        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
            $title_size = 31;
        } else {
            $maxW = $this->wPrint - $this->wCanhoto;
            $title_size = 40;
        }
        $w = $maxW / $campos_por_linha;

        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $texto = "CÁLCULO DO IMPOSTO";
        $this->pTextBox($x, $y, $title_size, 8, $texto, $aFont, 'T', 'L', 0, '');
        $y += 3;
        $h = 7;

        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "BASE DE CÁLC. DO ICMS", "vBC");
        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "VALOR DO ICMS", "vICMS");
        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "BASE DE CÁLC. ICMS S.T.", "vBCST");
        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "VALOR DO ICMS SUBST.", "vST");
        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "V. IMP. IMPORTAÇÃO", "vII");

        if ($this->exibirIcmsInterestadual) {
            $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "V. ICMS UF REMET.", "vICMSUFRemet");
            $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "V. FCP UF DEST.", "vFCPUFDest");
        }

        if ($this->exibirPIS) {
            $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "VALOR DO PIS", "vPIS");
        }

        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "V. TOTAL PRODUTOS", "vProd");

        //

        $y += $h;
        $x = $x_inicial;

        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "VALOR DO FRETE", "vFrete");
        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "VALOR DO SEGURO", "vSeg");
        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "DESCONTO", "vDesc");
        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "OUTRAS DESPESAS", "vOutro");
        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "VALOR TOTAL IPI", "vIPI");

        if ($this->exibirIcmsInterestadual) {
            $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "V. ICMS UF DEST.", "vICMSUFDest");
            $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "V. TOT. TRIB.", "vTotTrib");
        }

        if ($this->exibirPIS) {
            $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "VALOR DA COFINS", "vCOFINS");
        }
        $x = $this->pImpostoDanfeHelper($x, $y, $w, $h, "V. TOTAL DA NOTA", "vNF");

        return ($y+$h);
    } //fim impostoDANFE

    /**
     * transporteDANFE
     * Monta o campo de transportes da DANFE (retrato e paisagem)
     *
     * @name   transporteDANFE
     * @param  float $x Posição horizontal canto esquerdo
     * @param  float $y Posição vertical canto superior
     * @return float Posição vertical final
     */
    protected function pTransporteDANFE($x, $y)
    {
        $oldX = $x;
        if ($this->orientacao == 'P') {
            $maxW = $this->wPrint;
        } else {
            $maxW = $this->wPrint - $this->wCanhoto;
        }
        //#####################################################################
        //TRANSPORTADOR / VOLUMES TRANSPORTADOS
        $texto = "TRANSPORTADOR / VOLUMES TRANSPORTADOS";
        $w = $maxW;
        $h = 7;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        //NOME / RAZÃO SOCIAL
        $w1 = $maxW*0.29;
        $y += 3;
        $texto = 'NOME / RAZÃO SOCIAL';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w1, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (isset($this->transporta)) {
            $texto = ! empty($this->transporta->getElementsByTagName("xNome")->item(0)->nodeValue) ?
                    $this->transporta->getElementsByTagName("xNome")->item(0)->nodeValue : '';
        } else {
            $texto = '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w1, $h, $texto, $aFont, 'B', 'L');
        //FRETE POR CONTA
        $x += $w1;
        $w2 = $maxW*0.15;
        $texto = 'FRETE';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w2, $h, $texto, $aFont, 'T', 'L', 1, '');
        // PATCH 20: sem o grupo transp (XML de teste/ERP) o frete fica em branco, sem
        // supor "0-Por conta do Rem"
        if (empty($this->transp)) {
            $tipoFrete = 'ausente'; // não casa com nenhum case (null == 0 casaria)
            $texto = '';
        } else {
            $tipoFrete = ! empty($this->transp->getElementsByTagName("modFrete")->item(0)->nodeValue) ?
                $this->transp->getElementsByTagName("modFrete")->item(0)->nodeValue : '0';
        }
        switch ($tipoFrete) {
            case 0:
                $texto = "0-Por conta do Rem";
                break;
            case 1:
                $texto = "1-Por conta do Dest";
                break;
            case 2:
                $texto = "2-Por conta de Terceiros";
                break;
            case 3:
                $texto = "3-Próprio por conta do Rem";
                break;
            case 4:
                $texto = "4-Próprio por conta do Dest";
                break;
            case 9:
                $texto = "9-Sem Transporte";
                break;
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w2, $h, $texto, $aFont, 'C', 'C', 1, '');
        //CÓDIGO ANTT
        $x += $w2;
        $texto = 'CÓDIGO ANTT';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w2, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (isset($this->veicTransp)) {
            $texto = ! empty($this->veicTransp->getElementsByTagName("RNTC")->item(0)->nodeValue) ?
                    $this->veicTransp->getElementsByTagName("RNTC")->item(0)->nodeValue : '';
        } else {
            $texto = '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w2, $h, $texto, $aFont, 'B', 'C');
        //PLACA DO VEÍC
        $x += $w2;
        $texto = 'PLACA DO VEÍCULO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w2, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (isset($this->veicTransp)) {
            $texto = ! empty($this->veicTransp->getElementsByTagName("placa")->item(0)->nodeValue) ?
                    $this->veicTransp->getElementsByTagName("placa")->item(0)->nodeValue : '';
        } elseif (isset($this->reboque)) {
            $texto = ! empty($this->reboque->getElementsByTagName("placa")->item(0)->nodeValue) ?
                    $this->reboque->getElementsByTagName("placa")->item(0)->nodeValue : '';
        } else {
            $texto = '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w2, $h, $texto, $aFont, 'B', 'C');
        //UF
        $x += $w2;
        $w3 = round($maxW*0.04, 0);
        $texto = 'UF';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w3, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (isset($this->veicTransp)) {
            $texto = ! empty($this->veicTransp->getElementsByTagName("UF")->item(0)->nodeValue) ?
                    $this->veicTransp->getElementsByTagName("UF")->item(0)->nodeValue : '';
        } elseif (isset($this->reboque)) {
            $texto = ! empty($this->reboque->getElementsByTagName("UF")->item(0)->nodeValue) ?
                    $this->reboque->getElementsByTagName("UF")->item(0)->nodeValue : '';
        } else {
            $texto = '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w3, $h, $texto, $aFont, 'B', 'C');
        //CNPJ / CPF
        $x += $w3;
        $w = $maxW-($w1+3*$w2+$w3);
        $texto = 'CNPJ / CPF';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (isset($this->transporta)) {
            $texto = ! empty($this->transporta->getElementsByTagName("CNPJ")->item(0)->nodeValue) ?
                    $this->pFormat(
                        $this->transporta->getElementsByTagName("CNPJ")->item(0)->nodeValue,
                        "##.###.###/####-##"
                    ) : '';
            if ($texto == '') {
                $texto = ! empty($this->transporta->getElementsByTagName("CPF")->item(0)->nodeValue) ?
                        $this->pFormat(
                            $this->transporta->getElementsByTagName("CPF")->item(0)->nodeValue,
                            "###.###.###-##"
                        ) : '';
            }
        } else {
            $texto = '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w, $h, $texto, $aFont, 'B', 'C');
        //#####################################################################
        //ENDEREÇO
        $y += $h;
        $x = $oldX;
        $h = 7;
        $w1 = $maxW*0.44;
        $texto = 'ENDEREÇO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w1, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (isset($this->transporta)) {
            $texto = ! empty($this->transporta->getElementsByTagName("xEnder")->item(0)->nodeValue) ?
                    $this->transporta->getElementsByTagName("xEnder")->item(0)->nodeValue : '';
        } else {
            $texto = '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w1, $h, $texto, $aFont, 'B', 'L');
        //MUNICÍPIO
        $x += $w1;
        $w2 = round($maxW*0.30, 0);
        $texto = 'MUNICÍPIO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w2, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (isset($this->transporta)) {
            $texto = ! empty($this->transporta->getElementsByTagName("xMun")->item(0)->nodeValue) ?
                    $this->transporta->getElementsByTagName("xMun")->item(0)->nodeValue : '';
        } else {
            $texto = '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w2, $h, $texto, $aFont, 'B', 'C');
        //UF
        $x += $w2;
        $w3 = round($maxW*0.04, 0);
        $texto = 'UF';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w3, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (isset($this->transporta)) {
            $texto = ! empty($this->transporta->getElementsByTagName("UF")->item(0)->nodeValue) ?
                    $this->transporta->getElementsByTagName("UF")->item(0)->nodeValue : '';
        } else {
            $texto = '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w3, $h, $texto, $aFont, 'B', 'C');
        //INSCRIÇÃO ESTADUAL
        $x += $w3;
        $w = $maxW-($w1+$w2+$w3);
        $texto = 'INSCRIÇÃO ESTADUAL';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = '';
        if (isset($this->transporta)) {
            if (! empty($this->transporta->getElementsByTagName("IE")->item(0)->nodeValue)) {
                $texto = $this->transporta->getElementsByTagName("IE")->item(0)->nodeValue;
            }
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w, $h, $texto, $aFont, 'B', 'C');
        //Tratar Multiplos volumes
        $volumes = empty($this->transp) ? array() : $this->transp->getElementsByTagName('vol');
        $quantidade = 0;
        $especie = '';
        $marca = '';
        $numero = '';
        $texto = '';
        $pesoBruto=0;
        $pesoLiquido=0;
        // PATCH 20: qVol/pesoB/pesoL como o XML traz. "12,345" (vírgula decimal) virava
        // 12 (o PHP lê só o começo numérico) e "NaN" derrubava o DANFE (int + string).
        // Agora numeroVolume() lê ponto ou vírgula decimal; valor que não é número sai
        // literal (somaVolume), nunca somado nem trocado por zero.
        $brutos = $liquidos = $quantidades = array();
        foreach ($volumes as $volume) {
            $quantidades[] = $this->getTagValue($volume, "qVol");
            $brutos[] = $this->getTagValue($volume, "pesoB");
            $liquidos[] = $this->getTagValue($volume, "pesoL");
            $texto = ! empty($this->transp->getElementsByTagName("esp")->item(0)->nodeValue) ?
                    $this->transp->getElementsByTagName("esp")->item(0)->nodeValue : '';
            if ($texto != $especie && $especie != '') {
                //tem várias especies
                $especie = 'VARIAS';
            } else {
                $especie = $texto;
            }
            $texto = ! empty($this->transp->getElementsByTagName("marca")->item(0)->nodeValue) ?
                    $this->transp->getElementsByTagName("marca")->item(0)->nodeValue : '';
            if ($texto != $marca && $marca != '') {
                //tem várias especies
                $marca = 'VARIAS';
            } else {
                $marca = $texto;
            }
            $texto = ! empty($this->transp->getElementsByTagName("nVol")->item(0)->nodeValue) ?
                    $this->transp->getElementsByTagName("nVol")->item(0)->nodeValue : '';
            if ($texto != $numero && $numero != '') {
                //tem várias especies
                $numero = 'VARIOS';
            } else {
                $numero = $texto;
            }
        }

        $quantidade = $this->somaVolume($quantidades, 0);
        $pesoBruto = $this->somaVolume($brutos, 3);
        $pesoLiquido = $this->somaVolume($liquidos, 3);
        //#####################################################################
        //QUANTIDADE
        $y += $h;
        $x = $oldX;
        $h = 7;
        $w1 = round($maxW*0.10, 0);
        $texto = 'QUANTIDADE';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w1, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (!empty($quantidade)) {
            $texto = $quantidade;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
            $this->valorTransporte($x, $y, $w1, $h, $texto, $aFont, 'B', 'C');
        }
        //ESPÉCIE
        $x += $w1;
        $w2 = round($maxW*0.17, 0);
        $texto = 'ESPÉCIE';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w2, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = $especie;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w2, $h, $texto, $aFont, 'B', 'C');
        //MARCA
        $x += $w2;
        $texto = 'MARCA';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w2, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = ! empty($this->transp) && ! empty($this->transp->getElementsByTagName("marca")->item(0)->nodeValue) ?
                $this->transp->getElementsByTagName("marca")->item(0)->nodeValue : '';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w2, $h, $texto, $aFont, 'B', 'C');
        //NUMERAÇÃO
        $x += $w2;
        $texto = 'NUMERAÇÃO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w2, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = $numero;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w2, $h, $texto, $aFont, 'B', 'C');
        //PESO BRUTO
        $x += $w2;
        $w3 = round($maxW*0.20, 0);
        $texto = 'PESO BRUTO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w3, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = $pesoBruto; // PATCH 20: já formatado (ou literal) por somaVolume()
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w3, $h, $texto, $aFont, 'B', 'R');
        //PESO LÍQUIDO
        $x += $w3;
        $w = $maxW -($w1+3*$w2+$w3);
        $texto = 'PESO LÍQUIDO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        $texto = $pesoLiquido; // PATCH 20: já formatado (ou literal) por somaVolume()
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->valorTransporte($x, $y, $w, $h, $texto, $aFont, 'B', 'R');
        return ($y+$h);
    } //fim transporteDANFE



    protected function pDescricaoProdutoHelper($origem, $campo, $formato)
    {
        $valor_original = $origem->getElementsByTagName($campo)->item(0);
        if (!isset($valor_original)) {
            return "";
        }
        $valor_original = $valor_original->nodeValue;
        $valor = ! empty($valor_original) ? number_format((float)$valor_original, 2, ",", ".") : '';

        if ($valor != "") {
            return sprintf($formato, $valor);
        }
        return "";
    }

    /**
     * PATCH 10: maior fonte, de $max até $min (passos de 0,5), com a qual o texto
     * quebrado na largura $w cabe na altura $h. Texto longo nunca vira microtexto
     * nem invade a caixa vizinha; o que já cabia continua no tamanho original.
     *
     * @return array fonte no formato do pTextBox
     */
    protected function fonteQueCabe($texto, $w, $h, $max, $min, $estilo = '')
    {
        for ($s = $max; $s > $min; $s -= 0.5) {
            $f = array('font'=>$this->fontePadrao, 'size'=>$s, 'style'=>$estilo);
            if ($this->pGetNumLines($texto, $w, $f) * ($s / 72 * 25.4) <= $h) {
                return $f;
            }
        }
        return array('font'=>$this->fontePadrao, 'size'=>$min, 'style'=>$estilo);
    }

    /**
     * PATCH 10: valor de um campo de caixa com rótulo (destinatário). Se cabe em
     * uma linha no tamanho original, desenha exatamente como antes (o pTextBox
     * reduzia a fonte até caber, virando microtexto com endereço longo); se não
     * cabe, quebra em linhas abaixo do rótulo com a maior fonte que couber (mín. 4,5).
     */
    protected function valorQueCabe($x, $y, $w, $h, $texto, $size, $estilo, $vAlign, $hAlign)
    {
        $aFont = array('font'=>$this->fontePadrao, 'size'=>$size, 'style'=>$estilo);
        $this->pdf->SetFont($aFont['font'], $aFont['style'], $aFont['size']);
        if ($this->pdf->GetStringWidth(mb_convert_encoding(trim($texto), 'ISO-8859-1', 'UTF-8')) <= $w - 1) {
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, $vAlign, $hAlign, 0, '');
            return;
        }
        $hRotulo = 2.1;
        $aFont = $this->fonteQueCabe($texto, $w - 1, $h - $hRotulo, $size, 4.5, $estilo);
        $this->pTextBox($x + 0.5, $y + $hRotulo, $w - 1, $h - $hRotulo, $texto, $aFont, 'C', $hAlign, 0, '', false);
    }

    /**
     * PATCH 20: número de qVol/pesoB/pesoL lido com tolerância: ponto decimal do
     * schema ("1.234" = 1,234) ou vírgula decimal escrita pelo ERP ("12,345",
     * "1.234,5"). null quando não é número ("NaN", "N/D").
     */
    protected function numeroVolume($valor)
    {
        $v = trim((string) $valor);
        if (preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            return (float) $v;
        }
        if (preg_match('/^-?(\d{1,3}(\.\d{3})+|\d+)(,\d+)?$/', $v)) {
            return (float) str_replace(array('.', ','), array('', '.'), $v);
        }
        return null;
    }

    /**
     * PATCH 20: total dos volumes para o quadro. Todos numéricos: a soma (pesos
     * com 3 casas; quantidade como antes, sem formatação), '' se zero ou ausente.
     * Algum não numérico: os valores do XML, literais, separados por " + ".
     */
    protected function somaVolume(array $valores, $casas)
    {
        $valores = array_values(array_filter($valores, function ($v) {
            return $v !== '';
        }));
        $soma = 0;
        foreach ($valores as $v) {
            $n = $this->numeroVolume($v);
            if ($n === null) {
                return implode(' + ', $valores);
            }
            $soma += $n;
        }
        if ($soma <= 0) {
            return '';
        }
        return $casas ? number_format($soma, $casas, ",", ".") : $soma;
    }

    /**
     * PATCH 18: valor de caixa do quadro TRANSPORTADOR/VOLUMES. Cabe numa linha
     * (com 0,5 mm de folga de cada lado), mesmo reduzido até 4,5 pt: desenha numa
     * linha, como sempre. Se não cabe nem assim (razão social, marca ou numeração
     * de 60 caracteres), quebra abaixo do rótulo sem atravessar a borda.
     */
    protected function valorTransporte($x, $y, $w, $h, $texto, $aFont, $vAlign, $hAlign)
    {
        $maior = $texto === '' ? 0 : $this->larguraTexto($texto, $aFont['size'], $aFont['style']);
        if ($maior <= $w - 1) {
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, $vAlign, $hAlign, 0, '');
            return;
        }
        $s = floor($aFont['size'] * ($w - 1) / $maior * 2) / 2;
        if ($s >= 4.5) {
            $aFont['size'] = $s;
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, $vAlign, $hAlign, 0, '');
            return;
        }
        $hRotulo = 2.1;
        $aFont = $this->fonteQueCabe($texto, $w - 1, $h - $hRotulo, $aFont['size'], 4.5, $aFont['style']);
        $this->pTextBox($x + 0.5, $y + $hRotulo, $w - 1, $h - $hRotulo, $texto, $aFont, 'C', $hAlign, 0, '', false);
    }

    /**
     * PATCH 18: número, vencimento e valor da duplicata $k, como o quadro imprime.
     *
     * @return string[] [nDup, vencimento dd/mm/aaaa, 'R$ valor']
     */
    protected function dadosDuplicata($k)
    {
        $dup = $this->dup->item($k);
        $nDup = $this->getTagValue($dup, 'nDup');
        $dVenc = $this->getTagValue($dup, 'dVenc');
        $vDup = $this->getTagValue($dup, 'vDup');
        return array(
            $nDup,
            $dVenc !== '' ? $this->pYmd2dmy($dVenc) : '',
            $vDup !== '' ? 'R$ ' . number_format((float) $vDup, 2, ",", ".") : '',
        );
    }

    /**
     * PATCH 18: o texto cabe na célula da duplicata, ao lado do rótulo, com fonte >= 4,5?
     */
    protected function cabeNaCelulaDup($texto, $w, $rotulo)
    {
        return $this->larguraTexto($texto, 4.5, 'B') <= $w - $this->larguraTexto($rotulo, 6, '') - 1.5;
    }

    /**
     * PATCH 18: maior fonte (7 → 4,5, passos de 0,5) com que o valor cabe na
     * célula da duplicata ao lado do rótulo. Valor que já cabia: 7, como antes.
     */
    protected function fonteValorDup($texto, $w, $rotulo)
    {
        $livre = $w - $this->larguraTexto($rotulo, 6, '') - 1.5;
        for ($s = 7; $s > 4.5; $s -= 0.5) {
            if ($this->larguraTexto($texto, $s, 'B') <= $livre) {
                return $s;
            }
        }
        return 4.5;
    }

    /**
     * PATCH 18: largura do texto em mm, medida como o pTextBox desenha (Windows-1252).
     */
    protected function larguraTexto($texto, $size, $style = '')
    {
        $this->pdf->SetFont($this->fontePadrao, $style, $size);
        return $this->pdf->getStringWidth(mb_convert_encoding(trim((string) $texto), 'Windows-1252', 'UTF-8'));
    }

    /**
     * PATCH 18: separa as duplicatas entre o quadro FATURA/DUPLICATA (até 4
     * linhas; número e valor cabendo na célula com fonte >= 4,5) e um texto com
     * as demais, na ordem do XML, que vai para os dados adicionais. Antes, da 29ª
     * duplicata em diante (37ª na paisagem) nada era impresso.
     *
     * @return string '' quando todas cabem no quadro
     */
    protected function prepararDuplicatas()
    {
        $this->dupGrade = array();
        $total = $this->dup->length;
        if ($total == 0) {
            return '';
        }
        $porLinha = $this->orientacao == 'P' ? 7 : 9;
        $w = $this->orientacao == 'P' ? round($this->wPrint/7.018, 0)-1 : 28;
        $extra = array();
        for ($k = 0; $k < $total; $k++) {
            list($nDup, $dVenc, $vDup) = $this->dadosDuplicata($k);
            if (empty($extra) && count($this->dupGrade) < 4 * $porLinha
                && $this->cabeNaCelulaDup($nDup, $w, 'Num.') && $this->cabeNaCelulaDup($vDup, $w, 'Valor')) {
                $this->dupGrade[] = $k;
                continue;
            }
            $num = ($nDup != '0' && $nDup != '') ? $nDup : (string) ($k + 1);
            $extra[] = trim("$num VENC. $dVenc $vDup");
        }
        return empty($extra) ? '' : 'DUPLICATAS (CONTINUAÇÃO DA FATURA): ' . implode(' / ', $extra);
    }

    /**
     * PATCH 18: quebra o texto como o pTextBox quebraria na largura $w e devolve
     * [as primeiras $nLinhas linhas, o restante ('' se coube tudo)].
     *
     * @return string[]
     */
    protected function dividirTexto($texto, $w, $nLinhas, $aFont)
    {
        $t = mb_convert_encoding(
            html_entity_decode(trim($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'Windows-1252',
            'UTF-8'
        );
        $this->pdf->SetFont($aFont['font'], $aFont['style'], $aFont['size']);
        $this->pdf->wordWrap($t, $w);
        $linhas = explode("\n", $t);
        // de volta a UTF-8, com & escapado: o pTextBox decodifica as entidades de novo
        $volta = function ($ls) {
            return str_replace('&', '&amp;', mb_convert_encoding(implode("\n", $ls), 'UTF-8', 'Windows-1252'));
        };
        if (count($linhas) <= $nLinhas) {
            return array($texto, '');
        }
        return array($volta(array_slice($linhas, 0, $nLinhas)), $volta(array_slice($linhas, $nLinhas)));
    }

    /**
     * PATCH 18: número de linhas do texto quebrado na largura $w.
     */
    protected function contarLinhas($texto, $w, $aFont)
    {
        $t = mb_convert_encoding(
            html_entity_decode(trim($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'Windows-1252',
            'UTF-8'
        );
        $this->pdf->SetFont($aFont['font'], $aFont['style'], $aFont['size']);
        return $this->pdf->wordWrap($t, $w);
    }

    /**
     * PATCH 18: quadro "DADOS ADICIONAIS (CONTINUAÇÃO)" numa folha própria, com o
     * texto que não coube na folha 1. Devolve o que ainda sobrou.
     */
    protected function pDadosAdicionaisContinuacao($x, $y, $w, $h, $texto)
    {
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($x, $y, $w, 8, 'DADOS ADICIONAIS (CONTINUAÇÃO)', $aFont, 'T', 'L', 0, '');
        $y += 3;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, 'INFORMAÇÕES COMPLEMENTARES', $aFont, 'T', 'L', 1, '');
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
        $cabem = max(1, (int) floor(($h - 3 + 0.3) / (7 / 72 * 25.4)));
        list($agora, $resto) = $this->dividirTexto($texto, $w - 2, $cabem, $aFont);
        $this->pTextBox($x, $y + 3, $w - 2, $h - 3, $agora, $aFont, 'T', 'L', 0, '', false);
        return $resto;
    }

    /**
     * PATCH 9: altura estimada de um item na contagem de páginas do montaDANFE().
     * A versão base é a fórmula original; custom/DanfeRtc sobrescreve.
     *
     * @param \DOMElement $item <det>
     * @param float $w2 largura da coluna de descrição
     * @return float
     */
    protected function calculeHeight($item, $w2)
    {
        $fontProduto = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
        // PATCH 20: largura do desenho da descrição (coluna - 0,5 mm; pGetNumLines tira 0,2)
        $numlinhas = $this->pGetNumLines($this->pDescricaoProduto($item), $w2 - 0.3, $fontProduto);
        $h = round(($numlinhas * $this->pdf->fontSize) + ($numlinhas * 0.5), 2);
        // PATCH 18: mesma regra do pItensDANFE (largura da grade = $w2 / 0,28)
        return max($h, $this->alturaNumerosItem($item, $w2 / 0.28));
    }

    /**
     * PATCH 18: valor numérico de célula da grade do Clássico. Se cabe (com 0,5 mm
     * de folga de cada lado) na fonte original, desenha como sempre; senão usa a
     * maior fonte (mín. 4,5) que cabe e, só se nem assim couber (valores de 13
     * dígitos), quebra nos separadores de milhar: nunca no meio de um grupo, nunca
     * atravessando a borda da coluna.
     */
    protected function celulaNumeroItem($x, $y, $w, $h, $texto, $aFont, $vAlign, $hAlign)
    {
        if ($texto === '' || $this->larguraTexto($texto, $aFont['size'], $aFont['style']) <= $w - 1) {
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, $vAlign, $hAlign, 0, '');
            return;
        }
        $q = $this->quebraNumero($texto, $w - 1, $aFont);
        $aFont['size'] = $q['size'];
        $this->pTextBox($x + 0.5, $y, $w - 1, $h, implode("\n", $q['linhas']), $aFont, $vAlign, $hAlign, 0, '', false);
    }

    /**
     * PATCH 18: maior fonte (passos de 0,5, mín. 4,5) com que o número cabe em $w;
     * a 4,5 sem caber, linhas quebradas depois de "." ou ",".
     *
     * @return array{size: float, linhas: string[]}
     */
    protected function quebraNumero($texto, $w, $aFont)
    {
        for ($s = $aFont['size']; $s >= 4.5; $s -= 0.5) {
            if ($this->larguraTexto($texto, $s, $aFont['style']) <= $w) {
                return array('size' => $s, 'linhas' => array($texto));
            }
        }
        $linhas = array();
        $atual = '';
        foreach (preg_split('/(?<=[.,])/', $texto) as $pedaco) {
            if ($atual !== '' && $this->larguraTexto($atual . $pedaco, 4.5, $aFont['style']) > $w) {
                $linhas[] = $atual;
                $atual = '';
            }
            $atual .= $pedaco;
        }
        $linhas[] = $atual;
        return array('size' => 4.5, 'linhas' => $linhas);
    }

    /**
     * PATCH 20: distância mínima (mm) entre os textos de duas colunas vizinhas da
     * grade. Abaixo disso os dois valores, separados só pela linha fina, são lidos
     * como um só ("0,00 3.203,75") — o pdf.js junta textos a menos de 0,6 corpo.
     */
    const FOLGA_VIZINHOS_ITEM = 1.5;

    /**
     * PATCH 20: desenha as células de uma faixa de item do Clássico.
     *
     * Cada célula: tipo ('cod' código, 'desc' descrição, 'txt' NCM/CST/CFOP/UN,
     * 'num' valores), alinhamento, texto (null = não desenha), x e w. Regras, só
     * quando o texto NÃO cabia (o que cabia sai com a mesma chamada de antes):
     * - descrição quebra em w - 0,5: começa 0,5 mm para dentro e termina antes da
     *   linha (antes quebrava em w e a linha cheia passava até 0,5 mm da borda);
     * - código que não cabe em w - 1 a 7 pt: maior fonte >= 4,5 que cabe; senão
     *   quebra a 4,5 (a faixa cresce em alturaNumerosItem());
     * - números como no celulaNumeroItem() (Patch 18);
     * - dois textos vizinhos na mesma linha a menos de FOLGA_VIZINHOS_ITEM: reduz
     *   (passos de 0,5, mín. 4,5) o da direita — ou o da esquerda, se centralizado
     *   e o da direita não puder — até a folga existir.
     */
    protected function desenharFaixaItem(array $celulas, $y, $h, $aFont)
    {
        $min = 4.5;
        foreach ($celulas as $k => $c) {
            $c += array('size' => $aFont['size'], 'modo' => 'normal', 'linhas' => null, 'fixo' => false);
            $t = $c['texto'];
            if ($t === null || trim($t) === '' || $c['tipo'] === 'desc') {
                $c['fixo'] = true;
            } elseif ($c['tipo'] === 'num') {
                if ($this->larguraTexto($t, $c['size'], $aFont['style']) > $c['w'] - 1) {
                    $q = $this->quebraNumero($t, $c['w'] - 1, $aFont);
                    $c['size'] = $q['size'];
                    $c['modo'] = 'reduzido';
                    if (count($q['linhas']) > 1) {
                        $c['linhas'] = $q['linhas'];
                        $c['fixo'] = true;
                    }
                }
            } elseif ($c['tipo'] === 'cod') {
                if ($this->larguraTexto($t, $c['size'], $aFont['style']) > $c['w'] - 1) {
                    $c['modo'] = 'reduzido';
                    while ($c['size'] > $min && $this->larguraTexto($t, $c['size'], $aFont['style']) > $c['w'] - 1) {
                        $c['size'] -= 0.5;
                    }
                    if ($this->larguraTexto($t, $c['size'], $aFont['style']) > $c['w'] - 1) {
                        $c['modo'] = 'quebrado';
                        $c['fixo'] = true;
                    }
                }
            } elseif ($this->larguraTexto($t, $c['size'], $aFont['style']) > $c['w']) {
                // NCM/CST/CFOP/UN que não cabem: o pTextBox de sempre decide (reduz ou quebra)
                $c['fixo'] = true;
            }
            $celulas[$k] = $c;
        }
        // folga entre vizinhos (célula vazia não conta: o vizinho é o próximo texto)
        $ant = null;
        foreach (array_keys($celulas) as $k) {
            if ($celulas[$k]['texto'] === null || trim($celulas[$k]['texto']) === '') {
                continue;
            }
            if ($ant !== null) {
                $passos = 0;
                while ($this->folgaItem($celulas[$ant], $celulas[$k], $aFont) < self::FOLGA_VIZINHOS_ITEM - 0.001
                    && $passos++ < 10
                ) {
                    if (! $this->reduzVizinho($celulas[$k], array('R', 'C'), $min)
                        && ! $this->reduzVizinho($celulas[$ant], array('C'), $min)
                    ) {
                        break;
                    }
                }
            }
            $ant = $k;
        }
        foreach ($celulas as $c) {
            if ($c['texto'] === null) {
                continue;
            }
            $f = array('font' => $aFont['font'], 'size' => $c['size'], 'style' => $aFont['style']);
            if ($c['tipo'] === 'desc') {
                $this->pTextBox($c['x'], $y, $c['w'] - 0.5, $h, $c['texto'], $f, 'T', 'L', 0, '', false);
            } elseif ($c['modo'] === 'quebrado') {
                $this->pTextBox($c['x'] + 0.5, $y, $c['w'] - 1, $h, $c['texto'], $f, 'T', 'C', 0, '', false);
            } elseif ($c['modo'] === 'reduzido' && $c['tipo'] === 'num') {
                $texto = $c['linhas'] ? implode("\n", $c['linhas']) : $c['texto'];
                $this->pTextBox($c['x'] + 0.5, $y, $c['w'] - 1, $h, $texto, $f, 'T', $c['al'], 0, '', false);
            } else {
                $this->pTextBox($c['x'], $y, $c['w'], $h, $c['texto'], $f, 'T', $c['al'], 0, '');
            }
        }
    }

    /**
     * PATCH 20: início e fim (mm) do texto da célula, como o pTextBox o põe
     * (1ª linha; código quebrado e NCM/CST/CFOP/UN que não cabem: a coluna).
     *
     * @return float[] [início, fim]
     */
    protected function extensaoItem(array $c, $aFont)
    {
        $x = $c['x'];
        $w = $c['w'];
        if ($c['tipo'] === 'desc') {
            $t = mb_convert_encoding(
                html_entity_decode(trim($c['texto']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'Windows-1252',
                'UTF-8'
            );
            $this->pdf->SetFont($aFont['font'], $aFont['style'], $c['size']);
            $this->pdf->wordWrap($t, $w - 0.5);
            return array($x + 0.5, $x + 0.5 + $this->pdf->getStringWidth(trim(explode("\n", $t)[0])));
        }
        if ($c['linhas'] || $c['modo'] === 'quebrado' || ($c['fixo'] && $c['tipo'] === 'txt')) {
            return array($x, $x + $w);
        }
        $l = $this->larguraTexto($c['texto'], $c['size'], $aFont['style']);
        // número reduzido: caixa x + 0,5 / w - 1 (celulaNumeroItem); demais: caixa x / w
        $recuo = ($c['tipo'] === 'num' && $c['modo'] === 'reduzido') ? 0.5 : 0;
        if ($c['al'] === 'R') {
            return array($x + $w - $recuo - 0.5 - $l, $x + $w - $recuo - 0.5);
        }
        return array($x + ($w - $l) / 2, $x + ($w + $l) / 2);
    }

    /** PATCH 20: espaço livre (mm) entre o texto de $a e o de $b, à direita */
    protected function folgaItem(array $a, array $b, $aFont)
    {
        return $this->extensaoItem($b, $aFont)[0] - $this->extensaoItem($a, $aFont)[1];
    }

    /**
     * PATCH 20: reduz 0,5 pt o texto da célula, se ela abre espaço para o vizinho
     * desse jeito (alinhamento em $alinhamentos, uma linha, acima do mínimo).
     */
    protected function reduzVizinho(array &$c, array $alinhamentos, $min)
    {
        if ($c['fixo'] || ! in_array($c['al'], $alinhamentos, true) || $c['size'] - 0.5 < $min) {
            return false;
        }
        $c['size'] -= 0.5;
        return true;
    }

    /**
     * PATCH 18: altura que os números do item pedem na grade do Clássico quando
     * algum só cabe quebrado em linhas; 0 quando todos cabem numa linha.
     */
    protected function alturaNumerosItem($item, $w)
    {
        $prod = $item->getElementsByTagName("prod")->item(0);
        $imposto = $item->getElementsByTagName("imposto")->item(0);
        $ICMS = $imposto ? $imposto->getElementsByTagName("ICMS")->item(0) : null;
        $IPI = $imposto ? $imposto->getElementsByTagName("IPI")->item(0) : null;
        $nf = function ($no, $tag, $casas, $padrao) {
            $v = $no ? $this->getTagValue($no, $tag) : '';
            return ! empty($v) ? number_format((float) $v, $casas, ",", ".") : $padrao;
        };
        $cols = array(0.07, 0.06, 0.06, 0.06, 0.06, 0.05, 0.035);
        $textos = array(
            $nf($prod, 'qCom', 4, ''), $nf($prod, 'vUnCom', 4, ''), $nf($prod, 'vProd', 2, ''),
            $ICMS ? $nf($ICMS, 'vBC', 2, '0, 00') : '', $ICMS ? $nf($ICMS, 'vICMS', 2, '0, 00') : '',
            $nf($IPI, 'vIPI', 2, ''), $ICMS ? $nf($ICMS, 'pICMS', 2, '0, 00') : '',
        );
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
        $n = 1;
        foreach ($textos as $i => $texto) {
            $wc = round($w * $cols[$i], 0);
            if ($texto !== '' && $this->larguraTexto($texto, 7) > $wc - 1) {
                $n = max($n, count($this->quebraNumero($texto, $wc - 1, $aFont)['linhas']));
            }
        }
        // PATCH 20: código do produto que nem a 4,5 pt cabe numa linha (desenharFaixaItem)
        $wc = round($w * 0.09, 0);
        $cod = $prod ? $this->getTagValue($prod, 'cProd') : '';
        // (largura a 4,5 = largura a 7 × 4,5/7: medir a 4,5 trocaria a fonte corrente e
        // poria um Tf a mais no PDF de toda nota)
        if ($cod !== '' && $this->larguraTexto($cod, 7) * 4.5 / 7 > $wc - 1) {
            $n = max($n, $this->contarLinhas($cod, $wc - 1, array('font'=>$this->fontePadrao, 'size'=>4.5, 'style'=>'')));
        }
        // a coluna da alíquota do IPI é o resto da largura: sempre larga o bastante
        return $n > 1 ? round($n * 4.5 / 72 * 25.4 + 0.6, 2) : 0;
    }

    /**
     * descricaoProduto
     * Monta a string de descrição de cada Produto
     *
     * @name   descricaoProduto
     * @param  DOMNode itemProd
     * @return string descricao do produto
     */
    protected function pDescricaoProduto($itemProd)
    {
        $prod = $itemProd->getElementsByTagName('prod')->item(0);
        $ICMS = $itemProd->getElementsByTagName("ICMS")->item(0);
        $ICMSUFDest = $itemProd->getElementsByTagName("ICMSUFDest")->item(0);
        $impostos = '';

        if (!empty($ICMS)) {
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "vBCFCP", " BcFcp=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "pFCP", " pFcp=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "vFCP", " vFcp=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "pRedBC", " pRedBC=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "pMVAST", " IVA/MVA=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "pICMSST", " pIcmsSt=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "vBCST", " BcIcmsSt=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "vICMSST", " vIcmsSt=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "vBCFCPST", " BcFcpSt=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "pFCPST", " pFcpSt=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "vFCPST", " vFcpSt=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "vBCSTRet", " vBcStRet=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "pST", " pSt=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMS, "vICMSSTRet", " vIcmsStRet=%s");
        }
        if (!empty($ICMSUFDest)) {
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "pFCPUFDest", " pFCPUFDest=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "pICMSUFDest", " pICMSUFDest=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "pICMSInterPart", " pICMSInterPart=%s%%");
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "vFCPUFDest", " vFCPUFDest=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "vICMSUFDest", " vICMSUFDest=%s");
            $impostos .= $this->pDescricaoProdutoHelper($ICMSUFDest, "vICMSUFRemet", " vICMSUFRemet=%s");
        }
        $infAdProd = ! empty($itemProd->getElementsByTagName('infAdProd')->item(0)->nodeValue) ?
                substr($this->pAnfavea($itemProd->getElementsByTagName('infAdProd')->item(0)->nodeValue), 0, 500) : '';
        if (! empty($infAdProd)) {
            $infAdProd = trim($infAdProd);
            $infAdProd .= ' ';
        }
        $loteTxt ='';
        // PATCH 18: getElementsByTagName nunca devolve null, então o "rastro"
        // (lote, quantidade, fabricação e validade) nunca era lido. Agora: med
        // primeiro (PMC; texto igual ao de antes) e depois cada rastro.
        foreach (array("med", "rastro") as $grupoLote) {
            $rastro = $prod->getElementsByTagName($grupoLote);
            $i = 0;
            while ($i < $rastro->length) {
                $loteTxt .= $this->getTagValue($rastro->item($i), 'nLote', ' Lote: ');
                $loteTxt .= $this->getTagValue($rastro->item($i), 'qLote', ' Quant: ');
                $loteTxt .= $this->pSimpleGetDate($rastro->item($i), 'dFab', ' Fab: ');
                $loteTxt .= $this->pSimpleGetDate($rastro->item($i), 'dVal', ' Val: ');
                $loteTxt .= $this->getTagValue($rastro->item($i), 'vPMC', ' PMC: ');
                $i++;
            }
        }
        if ($loteTxt != '') {
            $loteTxt.= ' ';
        }
        //NT2013.006 FCI
        $nFCI = (! empty($itemProd->getElementsByTagName('nFCI')->item(0)->nodeValue)) ?
                ' FCI:'.$itemProd->getElementsByTagName('nFCI')->item(0)->nodeValue : '';
        $tmp_ad=$infAdProd . ($this->descProdInfoComplemento ? $loteTxt . $impostos . $nFCI : '');
        $texto = $prod->getElementsByTagName("xProd")->item(0)->nodeValue . (strlen($tmp_ad)!=0?"\n    ".$tmp_ad:'');
        // PATCH 18: veículo novo na descrição (o bloco antigo era desenhado abaixo
        // da faixa do item, sem altura reservada: os itens seguintes caíam por cima)
        $texto .= $this->textoVeiculo($itemProd);
        //decodifica os caracteres html no xml
        $texto = html_entity_decode($texto);
        if ($this->descProdQuebraLinha) {
            $texto = str_replace(";", "\n", $texto);
        }
        return $this->limitarDescricao($texto, $itemProd);
    }

    /**
     * PATCH 18: dados do veículo novo (veicProd), com as tabelas do RENAVAM.
     */
    protected function textoVeiculo($det)
    {
        $v = $det->getElementsByTagName('veicProd')->item(0);
        if (empty($v)) {
            return '';
        }
        $tabelas = array(
            'tpComb' => array(1=>'ALCOOL', 2=>'GASOLINA', 3=>'DIESEL', 4=>'GASOGENIO', 5=>'GAS METANO',
                6=>'ELETRICO/FONTE INTERNA', 7=>'ELETRICO/FONTE EXTERNA', 8=>'GASOL/GAS NATURAL COMBUSTIVEL',
                9=>'ALCOOL/GAS NATURAL COMBUSTIVEL', 10=>'DIESEL/GAS NATURAL COMBUSTIVEL', 11=>'VIDE/CAMPO/OBSERVACAO',
                12=>'ALCOOL/GAS NATURAL VEICULAR', 13=>'GASOLINA/GAS NATURAL VEICULAR', 14=>'DIESEL/GAS NATURAL VEICULAR',
                15=>'GAS NATURAL VEICULAR', 16=>'ALCOOL/GASOLINA', 17=>'GASOLINA/ALCOOL/GAS NATURAL', 18=>'GASOLINA/ELETRICO'),
            'tpVeic' => array(1=>'BICICLETA', 2=>'CICLOMOTOR', 3=>'MOTONETA', 4=>'MOTOCICLETA', 5=>'TRICICLO',
                6=>'AUTOMOVEL', 7=>'MICROONIBUS', 8=>'ONIBUS', 9=>'BONDE', 10=>'REBOQUE', 11=>'SEMI-REBOQUE',
                12=>'CHARRETE', 13=>'CAMIONETA', 14=>'CAMINHAO', 15=>'CARROCA', 16=>'CARRO DE MAO',
                17=>'CAMINHAO TRATOR', 18=>'TRATOR DE RODAS', 19=>'TRATOR DE ESTEIRAS', 20=>'TRATOR MISTO',
                21=>'QUADRICICLO', 22=>'CHASSI/PLATAFORMA', 23=>'CAMINHONETE', 24=>'SIDE-CAR', 25=>'UTILITARIO',
                26=>'MOTOR-CASA'),
            'espVeic' => array(1=>'PASSAGEIRO', 2=>'CARGA', 3=>'MISTO', 4=>'CORRIDA', 5=>'TRACAO', 6=>'ESPECIAL', 7=>'COLECAO'),
            'tpPint' => array('F'=>'FOSCA', 'S'=>'SÓLIDA', 'P'=>'PEROLIZADA', 'M'=>'METALICA'),
        );
        $campos = array(
            'CHASSI' => 'chassi', 'COR' => 'xCor', 'MOTOR' => 'nMotor', 'SÉRIE' => 'nSerie',
            'ANO MOD' => 'anoMod', 'ANO FAB' => 'anoFab', 'POTÊNCIA' => 'pot', 'CILINDRADAS' => 'cilin',
            'COMBUSTÍVEL' => 'tpComb', 'MARCA/MODELO' => 'cMod', 'TIPO' => 'tpVeic', 'ESPÉCIE' => 'espVeic',
            'PINTURA' => 'tpPint', 'CMT' => 'CMT', 'DIST. EIXOS' => 'dist', 'PESO LÍQ' => 'pesoL', 'PESO BRUTO' => 'pesoB',
        );
        $partes = array();
        foreach ($campos as $rotulo => $tag) {
            $valor = $this->getTagValue($v, $tag);
            if ($valor === '') {
                continue;
            }
            if (isset($tabelas[$tag])) {
                $chave = $tag === 'tpPint' ? $valor : (int) $valor;
                if (isset($tabelas[$tag][$chave])) {
                    $valor .= '-' . $tabelas[$tag][$chave];
                }
            }
            $partes[] = "$rotulo: $valor";
        }
        return empty($partes) ? '' : "\n" . implode('  ', $partes);
    }

    /**
     * PATCH 18: largura útil (mm), fonte e altura de linha da descrição do item,
     * e linhas fixas da faixa além dela. custom/DanfeRtc sobrescreve.
     *
     * @return array [largura, fonte, altura da linha, linhas extras]
     */
    protected function medidaDescricao()
    {
        $w = $this->orientacao == 'P' ? $this->wPrint : $this->wPrint - $this->wCanhoto;
        // PATCH 20: coluna - 0,5 mm, a largura em que o pItensDANFE desenha a descrição
        return array(round($w * 0.28, 0) - 0.5, array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>''), 7 / 72 * 25.4 + 0.5, 0);
    }

    /**
     * PATCH 18: descrição de item mais alta que uma folha inteira (500 rastros,
     * infAdProd com dezenas de ";") saía da página. Fica na faixa o que cabe numa
     * folha e o restante vai para os dados adicionais (que continuam em folhas
     * próprias, se preciso), com aviso nos dois lugares.
     */
    protected function limitarDescricao($texto, $det)
    {
        if (empty($this->hPrint) || empty($this->pdf)) {
            return $texto;
        }
        list($w, $aFont, $linhaH, $extras) = $this->medidaDescricao();
        $hDispo2 = $this->hPrint - 10 - ($this->hCabecalho + 5 + $this->hCabecalhoItens) - 4;
        $max = (int) floor(($hDispo2 - $this->hCabecalhoItens - 2) / $linhaH) - $extras;
        // PATCH 20: a altura da faixa conta as linhas com pGetNumLines() sobre o UTF-8
        // (acento pesa mais que no Windows-1252 desenhado). Descrição com $max linhas
        // desenhadas e $max + 1 contadas passava, a faixa não cabia na folha e o
        // pItensDANFE a empurrava até a última folha, onde os itens invadiam o rodapé.
        // Agora vale a maior das duas contagens, também depois do corte.
        $linhas = function ($t) use ($w, $aFont) {
            return max($this->contarLinhas($t, $w, $aFont), $this->pGetNumLines($t, $w + 0.2, $aFont));
        };
        if ($max < 2 || $linhas($texto) <= $max) {
            return $texto;
        }
        for ($n = $max - 1; $n >= 1; $n--) {
            list($cabe, $resto) = $this->dividirTexto($texto, $w, $n, $aFont);
            if ($n === 1 || $linhas($cabe . "\n(CONTINUA EM DADOS ADICIONAIS)") <= $max) {
                break;
            }
        }
        $this->descContinua[$det->getAttribute('nItem')] = $resto;
        return $cabe . "\n(CONTINUA EM DADOS ADICIONAIS)";
    }

    /**
     * itensDANFE
     * Monta o campo de itens da DANFE (retrato e paisagem)
     *
     * @name   itensDANFE
     * @param  float $x       Posição horizontal canto esquerdo
     * @param  float $y       Posição vertical canto superior
     * @param  float $nInicio Número do item inicial
     * @param  float $max     Número do item final
     * @param  float $hmax    Altura máxima do campo de itens em mm
     * @return float Posição vertical final
     */
    protected function pItensDANFE($x, $y, &$nInicio, $hmax, $pag = 0, $totpag = 0, $hCabecItens = 7)
    {
        $oldX = $x;
        $oldY = $y;
        $totItens = $this->det->length;
        if ($hmax < $hCabecItens + 1) {
            // PATCH 18: folha sem espaço nem para o cabeçalho da grade (dados
            // adicionais/duplicatas ocupando a folha 1): nada de caixa com altura
            // negativa, que jogava os blocos seguintes por cima do cabeçalho. Os
            // itens já foram contados para a folha seguinte.
            return $oldY;
        }
        //#####################################################################
        //DADOS DOS PRODUTOS / SERVIÇOS
        $texto = "DADOS DOS PRODUTOS / SERVIÇOS ";
        if ($this->orientacao == 'P') {
            $w = $this->wPrint;
        } else {
            if ($nInicio < 2) { // primeira página
                $w = $this->wPrint - $this->wCanhoto;
            } else { // páginas seguintes
                $w = $this->wPrint;
            }
        }
        $h = 4;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        $y += 3;
        //desenha a caixa dos dados dos itens da NF
        $hmax += 1;
        $texto = '';
        $this->pTextBox($x, $y, $w, $hmax);
        //##################################################################################
        // cabecalho LOOP COM OS DADOS DOS PRODUTOS
        //CÓDIGO PRODUTO
        $texto = "CÓDIGO PRODUTO";
        $w1 = round($w*0.09, 0);
        $h = 4;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w1, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w1, $y, $x+$w1, $y+$hmax);
        //DESCRIÇÃO DO PRODUTO / SERVIÇO
        $x += $w1;
        $w2 = round($w*0.28, 0);
        $texto = 'DESCRIÇÃO DO PRODUTO / SERVIÇO';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w2, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w2, $y, $x+$w2, $y+$hmax);
        //NCM/SH
        $x += $w2;
        $w3 = round($w*0.06, 0);
        $texto = 'NCM/SH';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w3, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w3, $y, $x+$w3, $y+$hmax);
        //O/CST ou O/CSOSN
        $x += $w3;
        $w4 = round($w*0.05, 0);
        $texto = 'O/CSOSN';//Regime do Simples CRT = 1 ou CRT = 2
        if ($this->getTagValue($this->emit, 'CRT') == '3') {
             $texto = 'O/CST';//Regime Normal
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w4, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w4, $y, $x+$w4, $y+$hmax);
        //CFOP
        $x += $w4;
        $w5 = round($w*0.04, 0);
        $texto = 'CFOP';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w5, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w5, $y, $x+$w5, $y+$hmax);
        //UN
        $x += $w5;
        $w6 = round($w*0.03, 0);
        $texto = 'UN';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w6, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w6, $y, $x+$w6, $y+$hmax);
        //QUANT
        $x += $w6;
        $w7 = round($w*0.07, 0);
        $texto = 'QUANT';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w7, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w7, $y, $x+$w7, $y+$hmax);
        //VALOR UNIT
        $x += $w7;
        $w8 = round($w*0.06, 0);
        $texto = 'VALOR UNIT';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w8, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w8, $y, $x+$w8, $y+$hmax);
        //VALOR TOTAL
        $x += $w8;
        $w9 = round($w*0.06, 0);
        $texto = 'VALOR TOTAL';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w9, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w9, $y, $x+$w9, $y+$hmax);
        //B.CÁLC ICMS
        $x += $w9;
        $w10 = round($w*0.06, 0);
        $texto = 'B.CÁLC ICMS';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w10, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w10, $y, $x+$w10, $y+$hmax);
        //VALOR ICMS
        $x += $w10;
        $w11 = round($w*0.06, 0);
        $texto = 'VALOR ICMS';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w11, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w11, $y, $x+$w11, $y+$hmax);
        //VALOR IPI
        $x += $w11;
        $w12 = round($w*0.05, 0);
        $texto = 'VALOR IPI';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w12, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w12, $y, $x+$w12, $y+$hmax);
        //ALÍQ. ICMS
        $x += $w12;
        $w13 = round($w*0.035, 0);
        $texto = 'ALÍQ. ICMS';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w13, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($x+$w13, $y, $x+$w13, $y+$hmax);
        //ALÍQ. IPI
        $x += $w13;
        $w14 = $w-($w1+$w2+$w3+$w4+$w5+$w6+$w7+$w8+$w9+$w10+$w11+$w12+$w13);
        $texto = 'ALÍQ. IPI';
        $this->pTextBox($x, $y, $w14, $h, $texto, $aFont, 'C', 'C', 0, '', false);
        $this->pdf->Line($oldX, $y+$h+1, $oldX + $w, $y+$h+1);
        $y += 5;
        //##################################################################################
        // LOOP COM OS DADOS DOS PRODUTOS
        $i = 0;
        $hUsado = $hCabecItens;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
        foreach ($this->det as $d) {
            if ($i >= $nInicio) {
                $thisItem = $this->det->item($i);
                //carrega as tags do item
                $prod = $thisItem->getElementsByTagName("prod")->item(0);
                $imposto = $this->det->item($i)->getElementsByTagName("imposto")->item(0);
                // PATCH 20: item sem <imposto> (XML de teste/ERP): colunas de imposto em branco
                $ICMS = $imposto ? $imposto->getElementsByTagName("ICMS")->item(0) : null;
                $IPI  = $imposto ? $imposto->getElementsByTagName("IPI")->item(0) : null;
                $textoProduto = trim($this->pDescricaoProduto($thisItem));

                // PATCH 20: conta na largura em que a descrição é desenhada (coluna - 0,5 mm)
                $linhaDescr = $this->pGetNumLines($textoProduto, $w2 - 0.3, $aFont);
                $h = round(($linhaDescr * $this->pdf->fontSize)+ ($linhaDescr * 0.5), 2);
                // PATCH 18: número que só cabe quebrado (valores de 13 dígitos) aumenta a faixa
                $h = max($h, $this->alturaNumerosItem($thisItem, $w));
                $hUsado += $h;

                $diffH = $hmax - $hUsado;

                if ($pag != $totpag) {
                    if (1 > $diffH && $i < $totItens) {
                        //ultrapassa a capacidade para uma única página
                        //o restante dos dados serão usados nas proximas paginas
                        $nInicio = $i;
                        break;
                    }
                }
                $y_linha=$y+$h;
                // linha entre itens
                $this->pdf->DashedHLine($oldX, $y_linha, $w, 0.1, 120);
                //corrige o x
                $x=$oldX;
                // PATCH 20: as células da faixa são montadas aqui e desenhadas por
                // desenharFaixaItem(): texto dentro da coluna (a descrição quebrava na
                // largura toda da coluna e passava 0,5 mm da borda) e valores de colunas
                // vizinhas nunca colados na linha. Texto que já cabia sai igual.
                // null = célula que o original não desenhava (sem ICMS).
                $num = function ($no, $tag, $padrao) {
                    return ! empty($no->getElementsByTagName($tag)->item(0)->nodeValue) ?
                        number_format((float)$no->getElementsByTagName($tag)->item(0)->nodeValue, 2, ",", ".") : $padrao;
                };
                $cst = null;
                if (isset($ICMS)) {
                    $cst = $this->getTagValue($ICMS, "orig") . $this->getTagValue($ICMS, "CST")
                        . $this->getTagValue($ICMS, "CSOSN");
                }
                $vProd = "";
                if (is_numeric($prod->getElementsByTagName("vProd")->item(0)->nodeValue)) {
                    $vProd = number_format((float)$prod->getElementsByTagName("vProd")->item(0)->nodeValue, 2, ",", ".");
                }
                $larguras = array($w1, $w2, $w3, $w4, $w5, $w6, $w7, $w8, $w9, $w10, $w11, $w12, $w13, $w14);
                $celulas = array(
                    array('cod', 'C', $prod->getElementsByTagName("cProd")->item(0)->nodeValue),
                    array('desc', 'L', $textoProduto),
                    array('txt', 'C', ! empty($prod->getElementsByTagName("NCM")->item(0)->nodeValue) ?
                        $prod->getElementsByTagName("NCM")->item(0)->nodeValue : ''),
                    array('txt', 'C', $cst),
                    array('txt', 'C', $prod->getElementsByTagName("CFOP")->item(0)->nodeValue),
                    array('txt', 'C', $prod->getElementsByTagName("uCom")->item(0)->nodeValue),
                    array('num', 'R', number_format((float)$prod->getElementsByTagName("qCom")->item(0)->nodeValue, 4, ",", ".")),
                    array('num', 'R', number_format((float)$prod->getElementsByTagName("vUnCom")->item(0)->nodeValue, 4, ",", ".")),
                    array('num', 'R', $vProd),
                    array('num', 'R', isset($ICMS) ? $num($ICMS, "vBC", '0, 00') : null),
                    array('num', 'R', isset($ICMS) ? $num($ICMS, "vICMS", '0, 00') : null),
                    array('num', 'R', isset($IPI) ? $num($IPI, "vIPI", '') : ''),
                    array('num', 'C', isset($ICMS) ? $num($ICMS, "pICMS", '0, 00') : null),
                    array('num', 'C', isset($IPI) ? $num($IPI, "pIPI", '') : ''),
                );
                foreach ($celulas as $k => $c) {
                    $celulas[$k] = array('tipo' => $c[0], 'al' => $c[1],
                        'texto' => $c[2] === null ? null : (string) $c[2], 'x' => $x, 'w' => $larguras[$k]);
                    $x += $larguras[$k];
                }
                $this->desenharFaixaItem($celulas, $y, $h, $aFont);


                // Dados do Veiculo Somente para veiculo 0 Km
                $veicProd = $prod->getElementsByTagName("veicProd")->item(0);
                // Tag somente é gerada para veiculo 0k, e só é permitido um veiculo por NF-e por conta do detran
                // Verifica se a Tag existe
                // PATCH 18: os dados do veículo agora vão na descrição (textoVeiculo());
                // pDadosItenVeiculoDANFE() fica só por compatibilidade


                $y += $h;
                $i++;
                //incrementa o controle dos itens processados.
                $this->qtdeItensProc++;
            } else {
                $i++;
            }
        }
        return $oldY+$hmax;
    }


    /**
     * pDadosItenVeiculoDANFE
     * Coloca os dados do veiculo abaixo do item da NFe. (retrato e paisagem)
     *
     * @param float  $x    Posição horizontal
     *                     canto esquerdo
     * @param float  $y    Posição vertical
     *                     canto superior
     * @param        $nInicio
     * @param float  $h    altura do campo
     * @param object $prod Contendo todos os dados do item
     */

    protected function pDadosItenVeiculoDANFE($x, $y, &$nInicio, $h, $prod)
    {
        $oldX = $x;
        $oldY = $y;

        if ($this->orientacao == 'P') {
            $w = $this->wPrint;
        } else {
            if ($nInicio < 2) { // primeira página
                $w = $this->wPrint - $this->wCanhoto;
            } else { // páginas seguintes
                $w = $this->wPrint;
            }
        }

        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');

        $w1 = round($w*0.09, 0);

        // Tabela Renavam Combustivel
        $renavamCombustivel = array(
            1=>'ALCOOL',
            2=>'GASOLINA',
            3=>'DIESEL',
            4=>'GASOGENIO',
            5=>'GAS METANO',
            6=>'ELETRICO/FONTE INTERNA',
            7=>'ELETRICO/FONTE EXTERNA',
            8=>'GASOL/GAS NATURAL COMBUSTIVEL',
            9=>'ALCOOL/GAS NATURAL COMBUSTIVEL',
            10=>'DIESEL/GAS NATURAL COMBUSTIVEL',
            11=>'VIDE/CAMPO/OBSERVACAO',
            12=>'ALCOOL/GAS NATURAL VEICULAR',
            13=>'GASOLINA/GAS NATURAL VEICULAR',
            14=>'DIESEL/GAS NATURAL VEICULAR',
            15=>'GAS NATURAL VEICULAR',
            16=>'ALCOOL/GASOLINA',
            17=>'GASOLINA/ALCOOL/GAS NATURAL',
            18=>'GASOLINA/ELETRICO'
        );

        $renavamEspecie = array(
            1=>'PASSAGEIRO',
            2=>'CARGA',
            3=>'MISTO',
            4=>'CORRIDA',
            5=>'TRACAO',
            6=>'ESPECIAL',
            7=>'COLECAO'
        );

        $renavamTiposVeiculos = array(
            1=>'BICICLETA',
            2=>'CICLOMOTOR',
            3=>'MOTONETA',
            4=>'MOTOCICLETA',
            5=>'TRICICLO',
            6=>'AUTOMOVEL',
            7=>'MICROONIBUS',
            8=>'ONIBUS',
            9=>'BONDE',
            10=>'REBOQUE',
            11=>'SEMI-REBOQUE',
            12=>'CHARRETE',
            13=>'CAMIONETA',
            14=>'CAMINHAO',
            15=>'CARROCA',
            16=>'CARRO DE MAO',
            17=>'CAMINHAO TRATOR',
            18=>'TRATOR DE RODAS',
            19=>'TRATOR DE ESTEIRAS',
            20=>'TRATOR MISTO',
            21=>'QUADRICICLO',
            22=>'CHASSI/PLATAFORMA',
            23=>'CAMINHONETE',
            24=>'SIDE-CAR',
            25=>'UTILITARIO',
            26=>'MOTOR-CASA'
        );

        $renavamTipoPintura = array(
            'F'=>'FOSCA',
            'S'=>'SÓLIDA',
            'P'=>'PEROLIZADA',
            'M'=>'METALICA',
        );



        $veicProd = $prod->getElementsByTagName("veicProd")->item(0);

        $veiculoChassi = $veicProd->getElementsByTagName("chassi")->item(0)->nodeValue;
        $veiculoCor = $veicProd->getElementsByTagName("xCor")->item(0)->nodeValue;
        $veiculoCilindrada = $veicProd->getElementsByTagName("cilin")->item(0)->nodeValue;
        $veiculoCmkg = $veicProd->getElementsByTagName("CMT")->item(0)->nodeValue;
        $veiculoTipo = $veicProd->getElementsByTagName("tpVeic")->item(0)->nodeValue;

        $veiculoMotor = $veicProd->getElementsByTagName("nMotor")->item(0)->nodeValue;
        $veiculoRenavam = $veicProd->getElementsByTagName("cMod")->item(0)->nodeValue;
        $veiculoHp = $veicProd->getElementsByTagName("pot")->item(0)->nodeValue;
        $veiculoPlaca = ''; //$veiculo->getElementsByTagName("CMT")->item(0)->nodeValue;
        $veiculoTipoPintura = $veicProd->getElementsByTagName("tpPint")->item(0)->nodeValue;

        $veiculoMarcaModelo = $prod->getElementsByTagName("xProd")->item(0)->nodeValue;
        $veiculoEspecie = $veicProd->getElementsByTagName("espVeic")->item(0)->nodeValue;
        $veiculoCombustivel = $veicProd->getElementsByTagName("tpComb")->item(0)->nodeValue;
        $veiculoSerial = $veicProd->getElementsByTagName("nSerie")->item(0)->nodeValue;
        $veiculoFabricacao = $veicProd->getElementsByTagName("anoFab")->item(0)->nodeValue;
        $veiculoModelo = $veicProd->getElementsByTagName("anoMod")->item(0)->nodeValue;

        $veiculoDistancia = $veicProd->getElementsByTagName("dist")->item(0)->nodeValue;

        $x = $oldX;

        $yVeic = $y + $h;
        $texto = 'Chassi: ............: ' . $veiculoChassi;
        $this->pTextBox($x, $yVeic, $w1+40, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Cor...................: ' . $veiculoCor;
        $this->pTextBox($x, $yVeic, $w1+40, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Cilindrada........: ' . $veiculoCilindrada;
        $this->pTextBox($x, $yVeic, $w1+40, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Cmkg...............: ' . $veiculoCmkg;
        $this->pTextBox($x, $yVeic, $w1+40, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Tipo.................: ' . $renavamTiposVeiculos[intval($veiculoTipo)];
        $this->pTextBox($x, $yVeic, $w1+40, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic = $y + $h;
        $xVeic = $x + 65;
        $texto = 'Nº Motor: .........: ' . $veiculoMotor;
        $this->pTextBox($xVeic, $yVeic, $w1+50, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Renavam...........: ' . $veiculoRenavam;
        $this->pTextBox($xVeic, $yVeic, $w1+50, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'HP.....................: ' . $veiculoHp;
        $this->pTextBox($xVeic, $yVeic, $w1+50, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Placa.................: ' . $veiculoPlaca;
        $this->pTextBox($xVeic, $yVeic, $w1+50, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Tipo Pintura......: ' . $renavamTipoPintura[$veiculoTipoPintura];
        $this->pTextBox($xVeic, $yVeic, $w1+50, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic = $y + $h;
        $xVeic = $xVeic + 55;
        $texto = 'Marca / Modelo.....: ' . $veiculoMarcaModelo;
        $this->pTextBox($xVeic, $yVeic, $w1+50, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Especie..................: ' . $renavamEspecie[intval($veiculoEspecie)];
        $this->pTextBox($xVeic, $yVeic, $w1+50, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Combustivel..........: ' . $renavamCombustivel[intval($veiculoCombustivel)];
        $this->pTextBox($xVeic, $yVeic, $w1+50, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Serial.....................: ' . $veiculoSerial;
        $this->pTextBox($xVeic, $yVeic, $w1+50, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Ano Fab/Mod........: '. $veiculoFabricacao . '/' . $veiculoModelo;
        $this->pTextBox($xVeic, $yVeic, $w1+50, $h, $texto, $aFont, 'T', 'L', 0, '');
        $yVeic += $h;
        $texto = 'Distancia Entre Eixos(mm)..: '. $veiculoDistancia;
        $this->pTextBox($xVeic, $yVeic, $w1+50, $h, $texto, $aFont, 'T', 'L', 0, '');
    }

    /**
     * issqnDANFE
     * Monta o campo de serviços do DANFE
     *
     * @name   issqnDANFE (retrato e paisagem)
     * @param  float $x Posição horizontal canto esquerdo
     * @param  float $y Posição vertical canto superior
     * @return float Posição vertical final
     */
    protected function pIssqnDANFE($x, $y)
    {
        $oldX = $x;
        //#####################################################################
        //CÁLCULO DO ISSQN
        $texto = "CÁLCULO DO ISSQN";
        $w = $this->wPrint;
        $h = 7;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 0, '');
        //INSCRIÇÃO MUNICIPAL
        $y += 3;
        $w = round($this->wPrint*0.23, 0);
        $texto = 'INSCRIÇÃO MUNICIPAL';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        //inscrição municipal
        $texto = ! empty($this->emit->getElementsByTagName("IM")->item(0)->nodeValue) ?
                $this->emit->getElementsByTagName("IM")->item(0)->nodeValue : '';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'L', 0, '');
        //VALOR TOTAL DOS SERVIÇOS
        $x += $w;
        $texto = 'VALOR TOTAL DOS SERVIÇOS';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (isset($this->ISSQNtot)) {
            $texto = ! empty($this->ISSQNtot->getElementsByTagName("vServ")->item(0)->nodeValue) ?
                    $this->ISSQNtot->getElementsByTagName("vServ")->item(0)->nodeValue : '';
            $texto = number_format((float)$texto, 2, ",", ".");
        } else {
            $texto = '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'R', 0, '');
        //BASE DE CÁLCULO DO ISSQN
        $x += $w;
        $texto = 'BASE DE CÁLCULO DO ISSQN';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (isset($this->ISSQNtot)) {
            $texto = ! empty($this->ISSQNtot->getElementsByTagName("vBC")->item(0)->nodeValue) ?
                    $this->ISSQNtot->getElementsByTagName("vBC")->item(0)->nodeValue : '';
            $texto = ! empty($texto) ? number_format((float)$texto, 2, ",", ".") : '';
        } else {
            $texto = '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'R', 0, '');
        //VALOR TOTAL DO ISSQN
        $x += $w;
        if ($this->orientacao == 'P') {
            $w = $this->wPrint - (3 * $w);
        } else {
            $w = $this->wPrint - (3 * $w)-$this->wCanhoto;
        }
        $texto = 'VALOR TOTAL DO ISSQN';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        if (isset($this->ISSQNtot)) {
            $texto = ! empty($this->ISSQNtot->getElementsByTagName("vISS")->item(0)->nodeValue) ?
                    $this->ISSQNtot->getElementsByTagName("vISS")->item(0)->nodeValue : '';
            $texto = ! empty($texto) ? number_format((float)$texto, 2, ",", ".") : '';
        } else {
            $texto = '';
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'B', 'R', 0, '');
        return ($y+$h+1);
    }

    /**
     *dadosAdicionaisDANFE
     * Coloca o grupo de dados adicionais da NFe. (retrato e paisagem)
     *
     * @name   dadosAdicionaisDANFE
     * @param  float $x Posição horizontal canto esquerdo
     * @param  float $y Posição vertical canto superior
     * @param  float $h altura do campo
     * @return float Posição vertical final (eixo Y)
     */
    protected function pDadosAdicionaisDANFE($x, $y, $h)
    {
        //##################################################################################
        //DADOS ADICIONAIS
        $texto = "DADOS ADICIONAIS";
        if ($this->orientacao == 'P') {
              $w = $this->wPrint;
        } else {
              $w = $this->wPrint-$this->wCanhoto;
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'B');
        $this->pTextBox($x, $y, $w, 8, $texto, $aFont, 'T', 'L', 0, '');
        //INFORMAÇÕES COMPLEMENTARES
        $texto = "INFORMAÇÕES COMPLEMENTARES";
        $y += 3;
        $w = $this->wAdic;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        //o texto com os dados adicionais foi obtido na função montaDANFE
        //e carregado em uma propriedade privada da classe
        //$this->wAdic com a largura do campo
        //$this->textoAdic com o texto completo do campo
        $y += 1;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
        $this->pTextBox($x, $y+2, $w-2, $h-3, $this->textoAdic, $aFont, 'T', 'L', 0, '', false);
        //RESERVADO AO FISCO
        $texto = "RESERVADO AO FISCO";
        $x += $w;
        $y -= 1;
        if ($this->orientacao == 'P') {
            $w = $this->wPrint-$w;
        } else {
            $w = $this->wPrint-$w-$this->wCanhoto;
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'B');
        $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'T', 'L', 1, '');
        //inserir texto informando caso de contingência
        // 1 - Normal - emissão normal;
        // 2 - Contingência FS - emissão em contingência com impressão do DANFE em Formulário de Segurança;
        // 3 - Contingência SCAN - emissão em contingência no Sistema de Contingência do Ambiente Nacional;
        // 4 - Contingência DPEC - emissão em contingência com envio da Declaração
        //     Prévia de Emissão em Contingência;
        // 5 - Contingência FS-DA - emissão em contingência com impressão do DANFE em Formulário de
        //     Segurança para Impressão de Documento Auxiliar de Documento Fiscal Eletrônico (FS-DA);
        // 6 - Contingência SVC-AN
        // 7 - Contingência SVC-RS
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
            case 6:
                $texto = 'CONTINGÊNCIA SVC-AN' . $dhCont . $xJust;
                break;
            case 7:
                $texto = 'CONTINGÊNCIA SVC-RS' . $dhCont . $xJust;
                break;
        }
        $y += 2;
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
        $this->pTextBox($x, $y, $w-2, $h-3, $texto, $aFont, 'T', 'L', 0, '', false);
        return $y+$h;
    }

    /**
     * pRodape
     * Monta o rodapé no final da DANFE com a data/hora de impressão e informações
     * sobre a API NfePHP
     *
     * @name   pRodape
     * @param  float $xInic  Posição horizontal canto esquerdo
     * @param  float $yFinal Posição vertical final para impressão
     * @return void
     */
    protected function pRodape($x, $y)
    {
        if ($this->orientacao == 'P') {
              $w = $this->wPrint;
        } else {
              $w = $this->wPrint-$this->wCanhoto;
              $x = $this->wCanhoto;
        }
        $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'I');
        $texto = "Impresso em ". date('d/m/Y') . " as " . date('H:i:s');
        $this->pTextBox($x, $y, $w, 0, $texto, $aFont, 'T', 'L', false);
        $texto = $this->creditos .  "  Powered by NFePHP";
        $this->pTextBox($x, $y, $w, 0, $texto, $aFont, 'T', 'R', false, '');
    }

    /**
     * pCcanhotoDANFE
     * Monta o canhoto da DANFE (retrato e paisagem)
     *
     * @name   canhotoDANFE
     * @param  number $x Posição horizontal canto esquerdo
     * @param  number $y Posição vertical canto superior
     * @return number Posição vertical final
     *
     * TODO 21/07/14 fmertins: quando orientação L-paisagem, o canhoto está sendo gerado incorretamente
     */
    protected function pCanhoto($x, $y)
    {
        $oldX = $x;
        $oldY = $y;
        //#################################################################################
        //canhoto
        //identificação do tipo de nf entrada ou saida
        $tpNF = $this->ide->getElementsByTagName('tpNF')->item(0)->nodeValue;
        // PATCH 20: dest e enderDest são opcionais no schema (XML de teste/ERP sem eles
        // derrubava o DANFE com "getElementsByTagName() on null"). Sem o grupo, só o
        // que existe: o nome sem endereço, ou nada — sem separadores soltos.
        $no = function ($pai, $tag) {
            $n = empty($pai) ? null : $pai->getElementsByTagName($tag)->item(0);
            return $n ? $n->nodeValue : '';
        };
        if ($tpNF == '0') {
            //NFe de Entrada
            $emitente = '';
            if (!empty($this->dest)) {
                $emitente .= $no($this->dest, "xNome");
            }
            if (!empty($this->enderDest)) {
                $emitente .= " - ";
                $emitente .= $no($this->enderDest, "xLgr") . ", ";
                $emitente .= $no($this->enderDest, "nro") . " - ";
                $emitente .= $this->getTagValue($this->enderDest, "xCpl", " - ", " ");
                $emitente .= $no($this->enderDest, "xBairro") . " ";
                $emitente .= $no($this->enderDest, "xMun") . "-";
                $emitente .= $no($this->enderDest, "UF") . "";
            }
            $destinatario = $no($this->emit, "xNome") . " ";
        } else {
            //NFe de Saída
            $emitente = $no($this->emit, "xNome") . " ";
            $destinatario = '';
            if (!empty($this->dest)) {
                $destinatario .= $no($this->dest, "xNome") . (empty($this->enderDest) ? " " : " - ");
            }
            if (!empty($this->enderDest)) {
                $destinatario .= $no($this->enderDest, "xLgr") . ", ";
                $destinatario .= $no($this->enderDest, "nro") . " ";
                $destinatario .= $this->getTagValue($this->enderDest, "xCpl", " - ", " ");
                $destinatario .= $no($this->enderDest, "xBairro") . " ";
                $destinatario .= $no($this->enderDest, "xMun") . "-";
                $destinatario .= $no($this->enderDest, "UF") . " ";
            }
        }
        //identificação do sistema emissor
        //linha separadora do canhoto
        if ($this->orientacao == 'P') {
            $w = round($this->wPrint * 0.81, 0);
        } else {
            //linha separadora do canhoto - 238
            //posicao altura
            $y = $this->wPrint-85;
            //altura
            $w = $this->wPrint-85-24;
        }
        $h = 10;
        //desenha caixa
        $texto = '';
        $aFont = array('font'=>$this->fontePadrao, 'size'=>7, 'style'=>'');
        $aFontSmall = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
        if ($this->orientacao == 'P') {
            $this->pTextBox($x, $y, $w, $h, $texto, $aFont, 'C', 'L', 1, '', false);
        } else {
            $this->pTextBox90($x, $y, $w, $h, $texto, $aFont, 'C', 'L', 1, '', false);
        }
        $numNF = str_pad($this->ide->getElementsByTagName('nNF')->item(0)->nodeValue, 9, "0", STR_PAD_LEFT);
        $serie = str_pad($this->ide->getElementsByTagName('serie')->item(0)->nodeValue, 3, "0", STR_PAD_LEFT);
        $texto = "RECEBEMOS DE ";
        $texto .= $emitente;
        $texto .= " OS PRODUTOS E/OU SERVIÇOS CONSTANTES DA NOTA FISCAL ELETRÔNICA INDICADA ";
        if ($this->orientacao == 'P') {
            $texto .= "ABAIXO";
        } else {
            $texto .= "AO LADO";
        }
        $texto .= ". EMISSÃO: ";
        $dEmi = ! empty($this->ide->getElementsByTagName("dEmi")->item(0)->nodeValue) ?
                $this->ide->getElementsByTagName("dEmi")->item(0)->nodeValue : '';
        if ($dEmi == '') {
            $dEmi = ! empty($this->ide->getElementsByTagName("dhEmi")->item(0)->nodeValue) ?
                    $this->ide->getElementsByTagName("dhEmi")->item(0)->nodeValue : '';
            $aDemi = explode('T', $dEmi);
            $dEmi = $aDemi[0];
        }
        $texto .= $this->pYmd2dmy($dEmi) ." ";
        $texto .= "VALOR TOTAL: R$ ";
        // PATCH 20: sem ICMSTot (grupo ausente) o valor fica em branco, não "0,00"
        $vNF = $this->getTagValue($this->ICMSTot, "vNF");
        $texto .= ($vNF === '' ? '' : number_format((float)$vNF, 2, ",", ".")) . " ";
        $texto .= "DESTINATÁRIO: ";
        $texto .= $destinatario;
        if ($this->orientacao == 'P') {
            // PATCH 10: com endereço longo o texto passava da caixa de 10 mm
            $aFont = $this->fonteQueCabe($texto, $w-1, $h-0.5, 7, 4.5);
            $this->pTextBox($x, $y, $w-1, $h, $texto, $aFont, 'C', 'L', 0, '', false);
            $x1 = $x + $w;
            $w1 = $this->wPrint - $w;
            $texto = "NF-e";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>14, 'style'=>'B');
            $this->pTextBox($x1, $y, $w1, 18, $texto, $aFont, 'T', 'C', 0, '');
            $texto = "Nº. " . $this->pFormat($numNF, "###.###.###") . " \n";
            $texto .= "Série $serie";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>10, 'style'=>'B');
            $this->pTextBox($x1, $y, $w1, 18, $texto, $aFont, 'C', 'C', 1, '');
            //DATA DE RECEBIMENTO
            $texto = "DATA DE RECEBIMENTO";
            $y += $h;
            $w2 = round($this->wPrint*0.17, 0); //35;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
            $this->pTextBox($x, $y, $w2, 8, $texto, $aFont, 'T', 'L', 1, '');
            //IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR
            $x += $w2;
            $w3 = $w-$w2;
            $texto = "IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR";
            $this->pTextBox($x, $y, $w3, 8, $texto, $aFont, 'T', 'L', 1, '');
            $x = $oldX;
            $y += 9;
            $this->pdf->DashedHLine($x, $y, $this->wPrint, 0.1, 80);
            $y += 2;
            return $y;
        } else {
            $x--;
            $aFontSmall = $this->fonteQueCabe($texto, $w-1, $h-0.5, 6, 4.5); // PATCH 10
            $x = $this->pTextBox90($x, $y, $w-1, $h, $texto, $aFontSmall, 'C', 'L', 0, '', false);
            //NUMERO DA NOTA FISCAL LOGO NFE
            $w1 = 18;
            $x1 = $oldX;
            $y = $oldY;
            $texto = "NF-e";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>14, 'style'=>'B');
            $this->pTextBox($x1, $y, $w1, 18, $texto, $aFont, 'T', 'C', 0, '');
            $texto = "Nº.\n" . $this->pFormat($numNF, "###.###.###") . " \n";
            $texto .= "Série $serie";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>8, 'style'=>'B');
            $this->pTextBox($x1, $y, $w1, 18, $texto, $aFont, 'C', 'C', 1, '');
            //DATA DO RECEBIMENTO
            $texto = "DATA DO RECEBIMENTO";
            $y = $this->wPrint-85;
            $x = 12;
            $w2 = round($this->wPrint*0.17, 0); //35;
            $aFont = array('font'=>$this->fontePadrao, 'size'=>6, 'style'=>'');
            $this->pTextBox90($x, $y, $w2, 8, $texto, $aFont, 'T', 'L', 1, '');
            //IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR
            $y -= $w2;
            $w3 = $w-$w2;
            $texto = "IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR";
            $aFont = array('font'=>$this->fontePadrao, 'size'=>5.7, 'style'=>'');
            $x = $this->pTextBox90($x, $y, $w3, 8, $texto, $aFont, 'T', 'L', 1, '');
            $this->pdf->DashedVLine(23, $oldY, 0.1, $this->wPrint-20, 67);
            return $x;
        }
    }

    /**
     * pGeraInformacoesDaTagCompra
     * Devolve uma string contendo informação sobre as tag <compra><xNEmp>, <xPed> e <xCont> ou string vazia.
     * Aviso: Esta função não leva em consideração dados na tag xPed do item.
     *
     * @name   pGeraInformacoesDaTagCompra
     * @return string com as informacoes dos pedidos.
     */
    protected function pGeraInformacoesDaTagCompra()
    {
        $saida = "";
        if (isset($this->compra)) {
            if (! empty($this->compra->getElementsByTagName("xNEmp")->item(0)->nodeValue)) {
                $saida .= " Nota de Empenho: " . $this->compra->getElementsByTagName("xNEmp")->item(0)->nodeValue;
            }
            if (! empty($this->compra->getElementsByTagName("xPed")->item(0)->nodeValue)) {
                $saida .= " Pedido: " . $this->compra->getElementsByTagName("xPed")->item(0)->nodeValue;
            }
            if (! empty($this->compra->getElementsByTagName("xCont")->item(0)->nodeValue)) {
                $saida .= " Contrato: " . $this->compra->getElementsByTagName("xCont")->item(0)->nodeValue;
            }
        }
        return $saida;
    }

    /**
     * pGeraChaveAdicionalDeContingencia
     *
     * @name   pGeraChaveAdicionalDeContingencia
     * @return string chave
     */
    protected function pGeraChaveAdicionalDeContingencia()
    {
        //cUF tpEmis CNPJ vNF ICMSp ICMSs DD  DV
        // Quantidade de caracteres  02   01      14  14    01    01  02 01
        $forma  = "%02d%d%s%014d%01d%01d%02d";
        $cUF    = $this->ide->getElementsByTagName('cUF')->item(0)->nodeValue;
        $CNPJ   = "00000000000000" . $this->emit->getElementsByTagName('CNPJ')->item(0)->nodeValue;
        $CNPJ   = substr($CNPJ, -14);
        // PATCH 18: round(): vNF * 100 em float (100.29 * 100 = 10028,999...) truncava no %014d e
        // a chave adicional do FS/FS-DA saía com o valor (e o DV) errados
        $vNF    = (int) round((float) $this->ICMSTot->getElementsByTagName("vNF")->item(0)->nodeValue * 100);
        $vICMS  = $this->ICMSTot->getElementsByTagName("vICMS")->item(0)->nodeValue;
        if ($vICMS > 0) {
            $vICMS = 1;
        }
        $icmss  = $this->ICMSTot->getElementsByTagName("vBC")->item(0)->nodeValue;
        if ($icmss > 0) {
            $icmss = 1;
        }
        $dEmi = ! empty($this->ide->getElementsByTagName("dEmi")->item(0)->nodeValue) ?
                $this->ide->getElementsByTagName("dEmi")->item(0)->nodeValue : '';
        if ($dEmi == '') {
            $dEmi = ! empty($this->ide->getElementsByTagName("dhEmi")->item(0)->nodeValue) ?
                    $this->ide->getElementsByTagName("dhEmi")->item(0)->nodeValue : '';
            $aDemi = explode('T', $dEmi);
            $dEmi = $aDemi[0];
        }
        $dd  = $dEmi;
        $rpos = strrpos($dd, '-');
        $dd  = substr($dd, $rpos +1);
        $chave = sprintf($forma, $cUF, $this->tpEmis, $CNPJ, $vNF, $vICMS, $icmss, $dd);
        $chave = $chave . $this->pModulo11($chave);
        return $chave;
    }

    /**
     * pGeraInformacoesDasNotasReferenciadas
     * Devolve uma string contendo informação sobre as notas referenciadas. Suporta N notas, eletrônicas ou não
     * Exemplo: NFe Ref.: série: 01 número: 01 emit: 11.111.111/0001-01
     * em 10/2010 [0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000]
     *
     * @return string Informacoes a serem adicionadas no rodapé sobre notas referenciadas.
     */
    protected function pGeraInformacoesDasNotasReferenciadas()
    {
        $formaNfeRef = "\r\nNFe Ref.: série:%d número:%d emit:%s em %s [%s]";
        $formaCTeRef = "\r\nCTe Ref.: série:%d número:%d emit:%s em %s [%s]";
        $formaNfRef = "\r\nNF  Ref.: série:%d numero:%d emit:%s em %s modelo: %d";
        $formaECFRef = "\r\nECF Ref.: modelo: %s ECF:%d COO:%d";
        $formaNfpRef = "\r\nNFP Ref.: série:%d número:%d emit:%s em %s modelo: %d IE:%s";
        $saida='';
        $nfRefs = $this->ide->getElementsByTagName('NFref');
        if (0 === $nfRefs->length) {
            return $saida;
        }
        if ($nfRefs->length > 2) {
            return 'Existem mais de 2 NF/NFe/ECF/NFP/CTe referenciadas, não serão exibidas na DANFE.';
        }
        foreach ($nfRefs as $nfRef) {
            if (empty($nfRef)) {
                continue;
            }
            $refNFe = $nfRef->getElementsByTagName('refNFe');
            foreach ($refNFe as $chave_acessoRef) {
                $chave_acesso = $chave_acessoRef->nodeValue;
                $chave_acessoF = $this->pFormat($chave_acesso, $this->formatoChave);
                $data = substr($chave_acesso, 4, 2)."/20".substr($chave_acesso, 2, 2);
                $cnpj = $this->pFormat(substr($chave_acesso, 6, 14), "##.###.###/####-##");
                $serie  = substr($chave_acesso, 22, 3);
                $numero = substr($chave_acesso, 25, 9);
                $saida .= sprintf($formaNfeRef, $serie, $numero, $cnpj, $data, $chave_acessoF);
            }
            $refNF = $nfRef->getElementsByTagName('refNF');
            foreach ($refNF as $umaRefNFe) {
                $data = $umaRefNFe->getElementsByTagName('AAMM')->item(0)->nodeValue;
                $cnpj = $umaRefNFe->getElementsByTagName('CNPJ')->item(0)->nodeValue;
                $mod = $umaRefNFe->getElementsByTagName('mod')->item(0)->nodeValue;
                $serie = $umaRefNFe->getElementsByTagName('serie')->item(0)->nodeValue;
                $numero = $umaRefNFe->getElementsByTagName('nNF')->item(0)->nodeValue;
                $data = substr($data, 2, 2) . "/20" . substr($data, 0, 2);
                $cnpj = $this->pFormat($cnpj, "##.###.###/####-##");
                $saida .= sprintf($formaNfRef, $serie, $numero, $cnpj, $data, $mod);
            }
            $refCTe = $nfRef->getElementsByTagName('refCTe');
            foreach ($refCTe as $chave_acessoRef) {
                $chave_acesso = $chave_acessoRef->nodeValue;
                $chave_acessoF = $this->pFormat($chave_acesso, $this->formatoChave);
                $data = substr($chave_acesso, 4, 2)."/20".substr($chave_acesso, 2, 2);
                $cnpj = $this->pFormat(substr($chave_acesso, 6, 14), "##.###.###/####-##");
                $serie  = substr($chave_acesso, 22, 3);
                $numero = substr($chave_acesso, 25, 9);
                $saida .= sprintf($formaCTeRef, $serie, $numero, $cnpj, $data, $chave_acessoF);
            }
            $refECF = $nfRef->getElementsByTagName('refECF');
            foreach ($refECF as $umaRefNFe) {
                $mod    = $umaRefNFe->getElementsByTagName('mod')->item(0)->nodeValue;
                $nECF   = $umaRefNFe->getElementsByTagName('nECF')->item(0)->nodeValue;
                $nCOO   = $umaRefNFe->getElementsByTagName('nCOO')->item(0)->nodeValue;
                $saida .= sprintf($formaECFRef, $mod, $nECF, $nCOO);
            }
            $refNFP = $nfRef->getElementsByTagName('refNFP');
            foreach ($refNFP as $umaRefNFe) {
                $data = $umaRefNFe->getElementsByTagName('AAMM')->item(0)->nodeValue;
                $cnpj = ! empty($umaRefNFe->getElementsByTagName('CNPJ')->item(0)->nodeValue) ?
                    $umaRefNFe->getElementsByTagName('CNPJ')->item(0)->nodeValue :
                    '';
                $cpf = ! empty($umaRefNFe->getElementsByTagName('CPF')->item(0)->nodeValue) ?
                        $umaRefNFe->getElementsByTagName('CPF')->item(0)->nodeValue : '';
                $mod = $umaRefNFe->getElementsByTagName('mod')->item(0)->nodeValue;
                $serie = $umaRefNFe->getElementsByTagName('serie')->item(0)->nodeValue;
                $numero = $umaRefNFe->getElementsByTagName('nNF')->item(0)->nodeValue;
                $ie = $umaRefNFe->getElementsByTagName('IE')->item(0)->nodeValue;
                $data = substr($data, 2, 2) . "/20" . substr($data, 0, 2);
                if ($cnpj == '') {
                    $cpf_cnpj = $this->pFormat($cpf, "###.###.###-##");
                } else {
                    $cpf_cnpj = $this->pFormat($cnpj, "##.###.###/####-##");
                }
                $saida .= sprintf($formaNfpRef, $serie, $numero, $cpf_cnpj, $data, $mod, $ie);
            }
        }
        return $saida;
    }
    
    private function imagePNGtoJPG($original)
    {
        $image = imagecreatefrompng($original);
        ob_start();
        imagejpeg($image, null, 100);
        imagedestroy($image);
        $stringdata = ob_get_contents(); // read from buffer
        ob_end_clean();
        return 'data://text/plain;base64,'.base64_encode($stringdata);
    }
}
