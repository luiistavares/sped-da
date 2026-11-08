<?php

namespace NFePHP\DA\MDFe;

/**
 * Esta classe gera do PDF do MDFDe, conforme regras e estruturas
 * estabelecidas pela SEFAZ.
 *
 * @category  Library
 * @package   nfephp-org/sped-da
 * @name      Damdfe.php
 * @copyright 2009-2016 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @link      http://github.com/nfephp-org/sped-da for the canonical source repository
 * @author    Leandro C. Lopez <leandro dot castoldi at gmail dot com>
 *
 * PATCH consultadanfe (PATCHES.md, "Damdfe.php — emitente certo e leiaute em fluxo"):
 * leiaute reescrito em fluxo com quebra de página, conforme o MOC MDF-e 3.00a,
 * Anexo II (o DAMDFE "poderá ter tantas folhas quantas forem necessárias para
 * discriminação da documentação fiscal eletrônica"). Regras:
 *  - emitente lido só de dentro de <emit> (SITE-10: antes pegava o 1º <CPF> do
 *    documento, que é o do condutor);
 *  - nenhum texto encolhe abaixo de 6 pt nem é cortado: célula cresce, linha que
 *    não cabe vai para a próxima folha (cabeçalho repetido) e texto maior que uma
 *    folha continua na seguinte;
 *  - tudo o que o MDF-e traz de relevante aparece: municípios de carregamento,
 *    percurso, veículos (tração + até 3 reboques), até 10 condutores, vale-pedágio,
 *    CIOT, contratantes, seguro com todas as averbações, produto predominante,
 *    lacres, todos os documentos vinculados por município de descarregamento,
 *    infCpl e infAdFisco;
 *  - situação: homologação, contingência, cancelado e encerrado (protMDFe ou
 *    evento anexado ao XML) com marca d'água e texto no quadro do protocolo.
 */

use Com\Tecnick\Barcode\Barcode;
use NFePHP\DA\Legacy\Dom;
use NFePHP\DA\Legacy\Common;
use NFePHP\DA\Legacy\Pdf;

class Damdfe extends Common
{
    use \NFePHP\DA\Common\XmlSanitizerTrait;

    //publicas
    public $logoAlign = 'L'; //alinhamento do logo
    public $yDados = 0;
    public $debugMode = 0; //ativa ou desativa o modo de debug
    //privadas
    protected $pdf; // objeto fpdf()
    protected $xml; // string XML NFe
    protected $logomarca = ''; // path para logomarca em jpg
    protected $errMsg = ''; // mesagens de erro
    protected $errStatus = false;// status de erro TRUE um erro ocorreu false sem erros
    protected $orientacao = 'P'; //orientação da DANFE P-Retrato ou L-Paisagem
    protected $papel = 'A4'; //formato do papel
    //destivo do arquivo pdf I-borwser, S-retorna o arquivo, D-força download, F-salva em arquivo local
    protected $destino = 'I';
    protected $pdfDir = ''; //diretorio para salvar o pdf com a opção de destino = F
    protected $fontePadrao = 'Times'; //Nome da Fonte para gerar o DANFE
    protected $version = '3.00a';
    protected $wPrint; //largura imprimivel
    protected $hPrint; //comprimento imprimivel
    protected $formatoChave = "#### #### #### #### #### #### #### #### #### #### ####";
    protected $margemInterna = 2;

    //variaveis da damdfe
    protected $id;
    protected $chMDFe;
    protected $tpAmb;
    protected $cOrgao;
    protected $xCondUso;
    protected $dhEvento;
    protected $cStat;
    protected $xMotivo;
    protected $CNPJDest = '';
    protected $dhRegEvento;
    protected $nProt;
    protected $tpEmis;
    protected $qrCodMDFe;
    //objetos
    private $dom;

    // PATCH consultadanfe: propriedades declaradas (eram dinâmicas — deprecated no PHP 8.2)
    protected $mdfeProc;
    protected $infMDFe;
    protected $ide;
    protected $emit;
    protected $enderEmit;
    protected $CPF = '';
    protected $CNPJ = '';
    protected $IE = '';
    protected $xNome = '';
    protected $xFant = '';
    protected $xLgr = '';
    protected $nro = '';
    protected $xCpl = '';
    protected $xBairro = '';
    protected $UF = '';
    protected $xMun = '';
    protected $CEP = '';
    protected $fone = '';
    protected $email = '';
    protected $mod = '';
    protected $serie = '';
    protected $dhEmi = '';
    protected $UFIni = '';
    protected $UFFim = '';
    protected $nMDF = '';
    protected $tot;
    protected $qMDFe = '';
    protected $qNFe = '';
    protected $qNF = '';
    protected $qCTe = '';
    protected $qCT = '';
    protected $qCarga = '';
    protected $cUnid = '';
    protected $vCarga = '';
    protected $infModal;
    protected $rodo;
    protected $aereo;
    protected $aquav;
    protected $ferrov;
    protected $ciot = '';
    protected $veicTracao;
    protected $veicReboque;
    protected $valePed = '';
    protected $infCpl = '';
    protected $infAdFisco = '';
    protected $dhRecbto = '';
    protected $condutor;

    // estado do leiaute em fluxo
    /** @var float largura útil */
    private $W = 196.0;
    /** @var float margem esquerda/superior */
    private $xIni = 7.0;
    private $yIni = 7.0;
    /** @var float cursor vertical */
    private $y = 7.0;
    /** @var float limite inferior do conteúdo (acima do rodapé) */
    private $yMax = 285.0;
    /** @var array{marcas: string[], protocolo: string[]} situação do documento */
    private $situacao = ['marcas' => [], 'protocolo' => []];
    /** @var string grupo (município) da linha corrente da tabela, repetido na folha seguinte */
    private $grupoAtual = '';
    /** @var string data/hora da impressão (fuso de São Paulo) */
    private $impressoEm = '';

    /** menor corpo de fonte usado no leiaute (pt) */
    const FONTE_MIN = 6.0;

    /**
     * __construct
     *
     * @param string $xmlfile Arquivo XML da MDFe
     * @param string $sOrientacao (Opcional) Orientação da impressão P-retrato L-Paisagem
     * @param string $sPapel Tamanho do papel (Ex. A4)
     * @param string $sPathLogo Caminho para o arquivo do logo
     * @param string $sDestino Estabelece a direção do envio do documento PDF I-browser D-browser com download S-
     * @param string $sDirPDF Caminho para o diretorio de armazenamento dos arquivos PDF
     * @param string $fonteDAMDFE Nome da fonte alternativa do DAnfe
     * @param integer $mododebug 0-Não 1-Sim e 2-nada (2 default)
     */
    public function __construct(
        $xmlfile = '',
        $sOrientacao = '',
        $sPapel = '',
        $sPathLogo = '',
        $sDestino = 'I',
        $sDirPDF = '',
        $fontePDF = '',
        $mododebug = 2
    ) {
        //define o caminho base da instalação do sistema
        if (!defined('PATH_ROOT')) {
            define('PATH_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
        }
        //ajuste do tempo limite de resposta do processo
        set_time_limit(1800);
        //definição do caminho para o diretorio com as fontes do FDPF
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', 'font/');
        }

        if (is_numeric($mododebug)) {
            $this->debugMode = $mododebug;
        }
        if ($this->debugMode) {
            //ativar modo debug
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
        } else {
            //desativar modo debug
            error_reporting(0);
            ini_set('display_errors', 'Off');
        }
        // PATCH consultadanfe: orientação/papel vazios viravam "Unknown page format"
        $this->orientacao = in_array($sOrientacao, ['P', 'L'], true) ? $sOrientacao : 'P';
        $this->papel = empty($sPapel) ? 'A4' : $sPapel;
        $this->pdf = '';
        $this->xml = $xmlfile;
        $this->logomarca = $sPathLogo;
        $this->destino = $sDestino;
        $this->pdfDir = $sDirPDF;
        // verifica se foi passa a fonte a ser usada
        if (empty($fontePDF)) {
            $this->fontePadrao = 'Times';
        } else {
            $this->fontePadrao = $fontePDF;
        }
        //se for passado o xml
        if (empty($xmlfile)) {
            $this->errMsg = 'Um caminho para o arquivo xml da MDFe deve ser passado!';
            $this->errStatus = true;
        }

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
        $this->mdfeProc = $this->dom->getElementsByTagName("mdfeProc")->item(0);
        $this->infMDFe = $this->dom->getElementsByTagName("infMDFe")->item(0);
        $this->ide = $this->dom->getElementsByTagName("ide")->item(0);
        $this->emit = $this->dom->getElementsByTagName("emit")->item(0);
        // PATCH consultadanfe (SITE-10): emitente vem só de <emit>. Antes era o 1º
        // <CPF> do documento inteiro — o do condutor — e o CNPJ do emitente sumia.
        $this->CPF = $this->tag($this->emit, 'CPF');
        $this->CNPJ = $this->CPF === '' ? $this->tag($this->emit, 'CNPJ') : '';
        $this->IE = $this->tag($this->emit, 'IE');
        $this->xNome = $this->tag($this->emit, 'xNome');
        $this->xFant = $this->tag($this->emit, 'xFant');
        $this->enderEmit = $this->emit ? $this->emit->getElementsByTagName("enderEmit")->item(0) : null;
        $this->xLgr = $this->tag($this->enderEmit, 'xLgr');
        $this->nro = $this->tag($this->enderEmit, 'nro');
        $this->xCpl = $this->tag($this->enderEmit, 'xCpl');
        $this->xBairro = $this->tag($this->enderEmit, 'xBairro');
        $this->UF = $this->tag($this->enderEmit, 'UF');
        $this->xMun = $this->tag($this->enderEmit, 'xMun');
        $this->CEP = $this->tag($this->enderEmit, 'CEP');
        $this->fone = $this->tag($this->enderEmit, 'fone');
        $this->email = $this->tag($this->enderEmit, 'email');
        $this->tpAmb = $this->tag($this->ide, 'tpAmb');
        $this->mod = $this->tag($this->ide, 'mod');
        $this->serie = $this->tag($this->ide, 'serie');
        $this->dhEmi = $this->tag($this->ide, 'dhEmi');
        $this->UFIni = $this->tag($this->ide, 'UFIni');
        $this->UFFim = $this->tag($this->ide, 'UFFim');
        $this->nMDF = $this->tag($this->ide, 'nMDF');
        $this->tpEmis = $this->tag($this->ide, 'tpEmis');
        $this->tot = $this->infMDFe ? $this->infMDFe->getElementsByTagName("tot")->item(0) : null;
        $this->qMDFe = $this->tag($this->tot, 'qMDFe');
        $this->qNFe = $this->tag($this->tot, 'qNFe');
        $this->qNF = $this->tag($this->tot, 'qNF');
        $this->qCTe = $this->tag($this->tot, 'qCTe');
        $this->qCT = $this->tag($this->tot, 'qCT');
        $this->qCarga = $this->tag($this->tot, 'qCarga');
        $this->cUnid = $this->tag($this->tot, 'cUnid');
        $this->vCarga = $this->tag($this->tot, 'vCarga');
        $this->infModal = $this->dom->getElementsByTagName("infModal")->item(0);
        $this->rodo = $this->dom->getElementsByTagName("rodo")->item(0);
        $this->aereo = $this->dom->getElementsByTagName("aereo")->item(0);
        $this->aquav = $this->dom->getElementsByTagName("aquav")->item(0);
        $this->ferrov = $this->dom->getElementsByTagName("ferrov")->item(0);
        $this->ciot = $this->tag($this->rodo, 'CIOT');
        $this->veicTracao = $this->dom->getElementsByTagName("veicTracao")->item(0);
        $this->veicReboque = $this->dom->getElementsByTagName("veicReboque");
        $this->valePed = "";
        if ($this->dom->getElementsByTagName("valePed")->item(0) != "") {
            $this->valePed = $this->dom->getElementsByTagName("valePed")->item(0)->getElementsByTagName("disp");
        }
        $infAdic = $this->infMDFe ? $this->infMDFe->getElementsByTagName('infAdic')->item(0) : null;
        $this->infCpl = $this->tag($infAdic, 'infCpl');
        $this->infAdFisco = $this->tag($infAdic, 'infAdFisco');
        $this->chMDFe = $this->infMDFe ? str_replace('MDFe', '', $this->infMDFe->getAttribute("Id")) : '';
        $this->qrCodMDFe = $this->dom->getElementsByTagName('qrCodMDFe')->item(0) ?
            trim($this->dom->getElementsByTagName('qrCodMDFe')->item(0)->nodeValue) : null;
        if (is_object($this->mdfeProc)) {
            $prot = $this->mdfeProc->getElementsByTagName('protMDFe')->item(0);
            $this->nProt = $this->tag($prot, 'nProt');
            $this->dhRecbto = $this->tag($prot, 'dhRecbto');
            $this->cStat = $this->tag($prot, 'cStat');
            $this->xMotivo = $this->tag($prot, 'xMotivo');
        }
    }//fim construct

    /**
     * buildMDFe
     */
    public function buildMDFe()
    {
        $this->pdf = new Pdf($this->orientacao, 'mm', $this->papel);
        $maxW = $this->orientacao == 'P' ? 210 : 297;
        $maxH = $this->orientacao == 'P' ? 297 : 210;
        $this->xIni = 7;
        $this->yIni = 7;
        //largura imprimivel em mm
        $this->wPrint = $maxW - 14;
        //comprimento imprimivel em mm
        $this->hPrint = $maxH - 14;
        $this->W = $this->wPrint;
        $this->yMax = $maxH - 12;
        $agora = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $this->impressoEm = $agora->format('d/m/Y H:i:s');
        $this->situacao = $this->situacaoMDFe();
        // estabelece contagem de paginas
        $this->pdf->AliasNbPages();
        // fixa as margens
        $this->pdf->SetMargins(7, 7, 7);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetDrawColor(0, 0, 0);
        $this->pdf->SetFillColor(255, 255, 255);
        // inicia o documento
        $this->pdf->Open();
        $this->pdf->AddPage($this->orientacao, $this->papel);
        $this->pdf->SetLineWidth(0.1);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->fundoDaFolha();
        $this->y = $this->yIni;

        // leiaute padrão do MOC
        $this->cabecalho();
        $this->identificacao();
        $this->quadroModal();
        if ($this->rodo) {
            $this->modalRodoviario();
        } elseif ($this->aereo) {
            $this->modalAereo();
        } elseif ($this->aquav) {
            $this->modalAquaviario();
        } elseif ($this->ferrov) {
            $this->modalFerroviario();
        }
        $this->observacao();
        // campos acrescentados abaixo do leiaute padrão (MOC Anexo II, 2.1)
        $this->percurso();
        $this->produtoPredominante();
        $this->seguro();
        $this->lacres();
        $this->documentosVinculados();
    }

    // ------------------------------------------------------------------
    // leitura do XML
    // ------------------------------------------------------------------

    /** Valor do 1º descendente $nome de $no ('' se não houver). */
    private function tag($no, string $nome): string
    {
        if (!$no instanceof \DOMElement) {
            return '';
        }
        $n = $no->getElementsByTagName($nome)->item(0);

        return $n ? trim($n->nodeValue) : '';
    }

    /** Valor do filho direto $nome (evita pegar o <UF>/<RNTRC> do <prop> no lugar do veículo). */
    private function filho($no, string $nome): string
    {
        $f = $this->filhos($no, $nome);

        return $f ? trim($f[0]->nodeValue) : '';
    }

    /** Filhos diretos de $no com o nome $nome. @return \DOMElement[] */
    private function filhos($no, string $nome): array
    {
        $r = [];
        if ($no instanceof \DOMElement) {
            foreach ($no->childNodes as $c) {
                if ($c instanceof \DOMElement && $c->localName === $nome) {
                    $r[] = $c;
                }
            }
        }

        return $r;
    }

    /** Todos os elementos $nome dentro de infMDFe. @return \DOMElement[] */
    private function todos(string $nome, $raiz = null): array
    {
        $raiz = $raiz ?? $this->infMDFe;
        if (!$raiz instanceof \DOMElement) {
            return [];
        }

        return iterator_to_array($raiz->getElementsByTagName($nome), false);
    }

    /**
     * Situação do MDF-e: homologação, contingência, cancelado (cStat 101 ou evento
     * 110111) e encerrado (cStat 132 ou evento 110112) — o evento conta quando vem
     * anexado ao XML (retEventoMDFe/procEventoMDFe) com cStat 135/136.
     */
    private function situacaoMDFe(): array
    {
        $marcas = [];
        $prot = [];
        $cancelado = $this->cStat === '101';
        $encerrado = $this->cStat === '132';
        $sub = ['cancelado' => '', 'encerrado' => ''];
        foreach ($this->dom->getElementsByTagName('infEvento') as $ev) {
            $cStat = $this->tag($ev, 'cStat');
            if (!in_array($cStat, ['135', '136'], true)) {
                continue;
            }
            $tp = $this->tag($ev, 'tpEvento');
            $txt = trim($this->dataHora($this->tag($ev, 'dhRegEvento')) . ' - Prot. ' . $this->tag($ev, 'nProt'), ' -');
            if ($tp === '110111') {
                $cancelado = true;
                $sub['cancelado'] = $txt;
            } elseif ($tp === '110112') {
                $encerrado = true;
                $sub['encerrado'] = $txt;
            }
        }
        if ($this->tpAmb === '2') {
            $marcas[] = ['SEM VALOR FISCAL', 44];
            $marcas[] = ['AMBIENTE DE HOMOLOGAÇÃO', 24];
            $prot[] = 'EMITIDO EM AMBIENTE DE HOMOLOGAÇÃO – SEM VALOR FISCAL';
        }
        if ($cancelado) {
            $marcas[] = ['MDF-e CANCELADO', 44];
            $prot[] = trim('MDF-e CANCELADO ' . $sub['cancelado']);
        } elseif ($encerrado) {
            $marcas[] = ['MDF-e ENCERRADO', 44];
            $prot[] = trim('MDF-e ENCERRADO ' . $sub['encerrado']);
        }
        if (!is_object($this->mdfeProc) || $this->nProt === '') {
            if ($this->tpEmis === '2') {
                $prot[] = 'EMISSÃO EM CONTINGÊNCIA';
            } else {
                $marcas[] = ['SEM PROTOCOLO DE AUTORIZAÇÃO', 26];
                $prot[] = 'MDF-e SEM PROTOCOLO DE AUTORIZAÇÃO DE USO';
            }
        } elseif ($this->cStat !== '' && !in_array($this->cStat, ['100', '101', '132'], true)) {
            $marcas[] = ['SEM AUTORIZAÇÃO DE USO', 32];
            $prot[] = $this->cStat . ' - ' . $this->xMotivo;
        }

        return ['marcas' => $marcas, 'protocolo' => $prot];
    }

    // ------------------------------------------------------------------
    // formatação
    // ------------------------------------------------------------------

    /** CNPJ (numérico ou alfanumérico) e CPF formatados; outro tamanho sai como veio. */
    private function documento(string $d): string
    {
        $d = strtoupper(trim($d));
        if (strlen($d) === 14) {
            return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/'
                . substr($d, 8, 4) . '-' . substr($d, 12, 2);
        }
        if (strlen($d) === 11) {
            return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
        }

        return $d;
    }

    /** CNPJ ou CPF de um grupo (o que existir), formatado. */
    private function cnpjCpf($no): string
    {
        foreach ($this->filhos($no, 'CNPJ') as $n) {
            return $this->documento($n->nodeValue);
        }
        foreach ($this->filhos($no, 'CPF') as $n) {
            return $this->documento($n->nodeValue);
        }
        foreach ($this->filhos($no, 'idEstrangeiro') as $n) {
            return trim($n->nodeValue);
        }

        return '';
    }

    /** "AAAA-MM-DDTHH:MM:SS-03:00" → "DD/MM/AAAA HH:MM:SS" (hora local do XML, sem converter fuso). */
    private function dataHora(string $dh): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}:\d{2}(?::\d{2})?))?/', $dh, $m)) {
            return $dh;
        }

        return trim("$m[3]/$m[2]/$m[1] " . ($m[4] ?? ''));
    }

    private function chaveFormatada(string $ch): string
    {
        return trim(chunk_split($ch, 4, ' '));
    }

    private function numero($v, int $dec = 2): string
    {
        return $v === '' ? '' : number_format((float)$v, $dec, ',', '.');
    }

    /** Texto do XML → Windows-1252 da fonte do FPDF (como o Patch 11 do Common::pTextBox). */
    private function cp($s): string
    {
        $s = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], (string)$s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $s);
        $s = html_entity_decode(trim($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }

    // ------------------------------------------------------------------
    // primitivas de desenho (sem encolher fonte: o espaço cresce)
    // ------------------------------------------------------------------

    private function fonte(float $size, string $style = ''): void
    {
        $this->pdf->SetFont($this->fontePadrao, $style, max($size, self::FONTE_MIN));
    }

    /** Altura de uma linha de texto, em mm. */
    private function lh(float $size): float
    {
        return max($size, self::FONTE_MIN) * 0.3528 * 1.18;
    }

    /** Quebra o texto (já em cp1252) em linhas que cabem na largura. @return string[] */
    private function quebra(string $txt, float $w, float $size, string $style = ''): array
    {
        if (trim($txt) === '') {
            return [];
        }
        $this->fonte($size, $style);
        $t = $txt;
        $this->pdf->WordWrap($t, max($w - 1.2, 2));

        return explode("\n", $t);
    }

    /** Escreve linhas a partir do topo $y. @return float altura usada */
    private function escreve(float $x, float $y, float $w, array $linhas, float $size, string $style = '', string $al = 'L'): float
    {
        $this->fonte($size, $style);
        $lh = $this->lh($size);
        foreach ($linhas as $i => $l) {
            $l = rtrim($l);
            $lx = $x + 0.6;
            if ($al !== 'L') {
                $lw = $this->pdf->GetStringWidth($l);
                $lx = $al === 'C' ? $x + ($w - $lw) / 2 : $x + $w - $lw - 0.6;
            }
            $this->pdf->Text($lx, $y + $lh * ($i + 1) - $lh * 0.27, $l);
        }

        return count($linhas) * $lh;
    }

    /**
     * Linha de campos rotulados (rótulo 6 pt + valor) com altura comum; o valor
     * longo quebra e a linha cresce. $campos: [rótulo, valor, largura, corpo, estilo, alinhamento].
     * @return float altura da linha
     */
    private function linhaCampos(float $x, float $y, array $campos, float $hMin = 9.5): float
    {
        $lhR = $this->lh(6);
        $soma = array_sum(array_column($campos, 2));
        $prep = [];
        $h = $hMin;
        foreach ($campos as $c) {
            [$rot, $val, $w, $size, $style, $al] = $c + [3 => 8, 4 => '', 5 => 'L'];
            $w = $w * $this->W / $soma;
            $ls = $this->quebra($this->cp($val), $w, $size, $style);
            $prep[] = [$rot, $ls, $w, $size, $style, $al];
            $h = max($h, $lhR + count($ls) * $this->lh($size) + 1);
        }
        foreach ($prep as [$rot, $ls, $w, $size, $style, $al]) {
            $this->pdf->Rect($x, $y, $w, $h);
            $this->escreve($x, $y + 0.2, $w, [$this->cp($rot)], 6);
            $this->escreve($x, $y + $lhR + 0.5, $w, $ls, $size, $style, $al);
            $x += $w;
        }

        return $h;
    }

    /** Marca d'água e rodapé da folha corrente (desenhados antes do conteúdo). */
    private function fundoDaFolha(): void
    {
        $marcas = $this->situacao['marcas'];
        if ($marcas) {
            $this->pdf->SetTextColor(215, 215, 215);
            $yy = $this->yIni + ($this->yMax - $this->yIni) * 0.55;
            foreach ($marcas as [$txt, $size]) {
                $this->fonte($size, 'B');
                $t = $this->cp($txt);
                while ($this->pdf->GetStringWidth($t) > $this->W && $size > 20) {
                    $size -= 2;
                    $this->fonte($size, 'B');
                }
                $yy += $size * 0.3528 * 0.75; // linha de base: desce a altura das maiúsculas desta linha
                $this->pdf->Text($this->xIni + ($this->W - $this->pdf->GetStringWidth($t)) / 2, $yy, $t);
                $yy += $size * 0.3528 * 0.45;
            }
            $this->pdf->SetTextColor(0, 0, 0);
        }
        $yr = $this->yMax + 1.2;
        $this->escreve($this->xIni, $yr, $this->W, [$this->cp('Impresso em ' . $this->impressoEm)], 6, 'I');
        $this->escreve(
            $this->xIni,
            $yr,
            $this->W,
            [$this->cp('DAMDFE ' . $this->numeroMDFe() . ' - Folha ' . $this->pdf->PageNo() . '/{nb}')],
            6,
            'I',
            'R'
        );
    }

    private function numeroMDFe(): string
    {
        return 'Nº ' . $this->nMDFFormatado() . ' Série ' . $this->serie;
    }

    private function nMDFFormatado(): string
    {
        $n = str_pad($this->nMDF, 9, '0', STR_PAD_LEFT);

        return substr($n, 0, -6) . '.' . substr($n, -6, 3) . '.' . substr($n, -3);
    }

    /** Abre folha nova com o cabeçalho de continuação. */
    private function novaPagina(): void
    {
        $this->pdf->AddPage($this->orientacao, $this->papel);
        $this->pdf->SetLineWidth(0.1);
        $this->fundoDaFolha();
        $x = $this->xIni;
        $y = $this->yIni;
        $h = $this->linhaCampos($x, $y, [
            ['DAMDFE - continuação', $this->xNome, 70, 8, 'B'],
            ['Número / Série', $this->numeroMDFe(), 30, 8],
            ['Emissão', $this->dataHora($this->dhEmi), 28, 8],
            ['Chave de acesso', $this->chaveFormatada($this->chMDFe), 68, 7.5],
        ], 8);
        $this->y = $y + $h + 2;
    }

    /** Garante $h mm livres na folha (senão abre outra). */
    private function espaco(float $h): void
    {
        if ($this->y + $h > $this->yMax) {
            $this->novaPagina();
        }
    }

    /**
     * Tabela em fluxo: cabeçalho repetido a cada folha, linhas crescem para caber
     * o texto (nunca encolhe) e linha maior que a folha continua na seguinte.
     *
     * @param array $cols   [[título, largura relativa, alinhamento], ...]; título '' = sem linha de títulos
     * @param array $linhas lista de células (UTF-8) ou ['grupo' => texto] (linha de largura total, negrito)
     */
    private function tabela(string $titulo, array $cols, array $linhas, float $size = 7.5): void
    {
        if (!$linhas) {
            return;
        }
        $x = $this->xIni;
        $soma = array_sum(array_column($cols, 1));
        foreach ($cols as $i => $c) {
            $cols[$i][1] = $c[1] * $this->W / $soma;
        }
        $comTitulos = count(array_filter(array_column($cols, 0), fn($t) => $t !== '')) > 0;
        $lh = $this->lh($size);
        $this->grupoAtual = '';
        $cabecalho = function (bool $continua) use ($titulo, $cols, $comTitulos, $x) {
            $t = $titulo . ($continua ? ' (continuação)' : '');
            if ($t !== '') {
                $this->y += $this->escreve($x, $this->y, $this->W, [$this->cp($t)], 7, 'B');
            }
            if ($comTitulos) {
                $ls = [];
                foreach ($cols as $i => $c) {
                    $ls[$i] = $this->quebra($this->cp($c[0]), $c[1], 6.5, 'B');
                }
                $h = max(array_map('count', $ls)) * $this->lh(6.5) + 0.8;
                $cx = $x;
                foreach ($cols as $i => $c) {
                    $this->pdf->Rect($cx, $this->y, $c[1], $h);
                    $this->escreve($cx, $this->y + 0.4, $c[1], $ls[$i], 6.5, 'B', $c[2]);
                    $cx += $c[1];
                }
                $this->y += $h;
            }
        };
        $this->espaco(4 + ($comTitulos ? 4 : 0) + 3 * $lh + 1);
        $cabecalho(false);
        foreach ($linhas as $linha) {
            if (isset($linha['grupo'])) {
                $cel = [[$linha['grupo'], $this->W, 'L', 'B']];
                $this->grupoAtual = $linha['grupo'];
            } elseif (isset($linha['linha'])) {
                $cel = [[$linha['linha'], $this->W, 'L', '']];
            } else {
                $cel = [];
                foreach ($cols as $i => $c) {
                    $cel[] = [(string)($linha[$i] ?? ''), $c[1], $c[2], ''];
                }
            }
            $partes = [];
            foreach ($cel as $i => [$txt, $w, $al, $st]) {
                $partes[$i] = $this->quebra($this->cp($txt), $w, $size, $st);
            }
            $grupo = isset($linha['grupo']);
            while (true) {
                $resto = max(1, max(array_map('count', $partes)));
                $cabe = (int)floor(($this->yMax - $this->y - 0.8) / $lh);
                // linha curta (até 6 linhas de texto) não se parte: vai inteira para a
                // folha seguinte; a longa continua na seguinte se couberem 3+ linhas aqui.
                // Grupo (município) nunca fica sozinho no pé da folha.
                if ($cabe < ($grupo ? 2 : 1) || ($resto > $cabe && ($resto <= 6 || $cabe < 3))) {
                    $this->novaPagina();
                    $cabecalho(true);
                    if (!$grupo && $this->grupoAtual !== '') {
                        $this->linhaGrupo($this->grupoAtual . ' (continuação)', $size);
                    }
                    continue;
                }
                $n = min($resto, $cabe);
                $h = $n * $lh + 0.8;
                $cx = $x;
                foreach ($cel as $i => [$txt, $w, $al, $st]) {
                    $this->pdf->Rect($cx, $this->y, $w, $h);
                    $this->escreve($cx, $this->y + 0.4, $w, array_slice($partes[$i], 0, $n), $size, $st, $al);
                    $partes[$i] = array_slice($partes[$i], $n);
                    $cx += $w;
                }
                $this->y += $h;
                if (max(array_map('count', $partes)) === 0) {
                    break;
                }
            }
        }
        $this->y += 2;
    }

    private function linhaGrupo(string $txt, float $size): void
    {
        $ls = $this->quebra($this->cp($txt), $this->W, $size, 'B');
        $h = count($ls) * $this->lh($size) + 0.8;
        $this->pdf->Rect($this->xIni, $this->y, $this->W, $h);
        $this->escreve($this->xIni, $this->y + 0.4, $this->W, $ls, $size, 'B');
        $this->y += $h;
    }

    // ------------------------------------------------------------------
    // quadros
    // ------------------------------------------------------------------

    /** Emitente + título à esquerda, QR Code à direita. */
    private function cabecalho(): void
    {
        $x = $this->xIni;
        $y = $this->yIni;
        $temQr = !empty($this->qrCodMDFe);
        $wQr = 32;
        $wE = $temQr ? $this->W - $wQr - 2 : $this->W;
        $xt = $x;
        $wt = $wE;
        $hLogo = 0;
        if (is_file($this->logomarca)) {
            $info = getimagesize($this->logomarca);
            if ($info && $info[0] > 0) {
                $lw = min(30, $wE / 4);
                $hLogo = min(24, $lw * $info[1] / $info[0]);
                $this->pdf->Image($this->logomarca, $x + 1, $y + 1, $lw, $hLogo);
                $xt = $x + $lw + 2;
                $wt = $wE - $lw - 2;
            }
        }
        $nome = $this->quebra($this->cp($this->xNome), $wt, 10, 'B');
        $doc = $this->CPF !== '' ? 'CPF: ' . $this->documento($this->CPF) : 'CNPJ: ' . $this->documento($this->CNPJ);
        $l2 = $doc . ' - IE: ' . $this->IE;
        $end = $this->xLgr . ($this->nro !== '' ? ', ' . $this->nro : '')
            . ($this->xCpl !== '' ? ' - ' . $this->xCpl : '')
            . ($this->xBairro !== '' ? ' - ' . $this->xBairro : '');
        $cep = $this->CEP !== '' ? ' - CEP: ' . substr($this->CEP, 0, 5) . '-' . substr($this->CEP, 5) : '';
        $mun = $this->xMun . ' - ' . $this->UF . $cep;
        $contato = trim(($this->fone !== '' ? 'Fone: ' . $this->fone : '')
            . ($this->email !== '' ? '   E-mail: ' . $this->email : ''));
        $resto = [];
        foreach ([$l2, $end, $mun, $contato] as $t) {
            foreach ($this->quebra($this->cp($t), $wt, 8) as $l) {
                $resto[] = $l;
            }
        }
        $titulo = $this->quebra(
            $this->cp('DAMDFE - Documento Auxiliar de Manifesto Eletrônico de Documentos Fiscais'),
            $wE,
            9,
            'BI'
        );
        $hTit = count($titulo) * $this->lh(9) + 1.5;
        $hE = max(count($nome) * $this->lh(10) + count($resto) * $this->lh(8) + 2, $hLogo + 2);
        if ($temQr) {
            $hE = max($hE, $wQr - 2 - $hTit);
        }
        $this->pdf->RoundedRect($x, $y, $wE, $hE, 0.8, '1234', 'D');
        $yy = $y + 0.8;
        $yy += $this->escreve($xt, $yy, $wt, $nome, 10, 'B');
        $this->escreve($xt, $yy, $wt, $resto, 8);
        $yT = $y + $hE + 2;
        $this->pdf->RoundedRect($x, $yT, $wE, $hTit, 0.8, '1234', 'D');
        $this->escreve($x, $yT + 0.7, $wE, $titulo, 9, 'BI', 'C');
        $fim = $yT + $hTit;
        if ($temQr) {
            $xq = $x + $this->W - $wQr;
            $hq = max($wQr, $fim - $y);
            $this->pdf->RoundedRect($xq, $y, $wQr, $hq, 0.8, '1234', 'D');
            $this->pQRDAMDFE($xq + 1, $y + ($hq - 30) / 2);
            $fim = max($fim, $y + $hq);
        }
        $this->y = $fim + 2;
    }

    private function identificacao(): void
    {
        $h = $this->linhaCampos($this->xIni, $this->y, [
            ['Modelo', $this->mod, 16, 9, '', 'C'],
            ['Série', $this->serie, 14, 9, '', 'C'],
            ['Número', $this->nMDFFormatado(), 30, 9, '', 'C'],
            ['FL', '1/{nb}', 18, 9, '', 'C'],
            ['Data e Hora de Emissão', $this->dataHora($this->dhEmi), 50, 9, 'B', 'C'],
            ['UF Carreg.', $this->UFIni, 34, 9, 'B', 'C'],
            ['UF Descar.', $this->UFFim, 34, 9, 'B', 'C'],
        ]);
        $this->y += $h + 2;
    }

    /** Modal, quantidades, protocolo (esquerda) e controle do fisco (direita). */
    private function quadroModal(): void
    {
        $x = $this->xIni;
        $y = $this->y;
        // metades encostadas como no leiaute original: a borda do quadro do fisco
        // fica a 5,5 mm da 1ª barra (zona de silêncio do CODE-128 > 10 módulos)
        $wl = $this->W / 2;
        $xr = $x + $this->W / 2;
        $wr = $this->W / 2;
        $modal = $this->rodo ? 'Rodoviário' : ($this->aereo ? 'Aéreo' : ($this->aquav ? 'Aquaviário'
            : ($this->ferrov ? 'Ferroviário' : '')));
        $W = $this->W;
        // as linhas de campos usam $this->W como base: escala para a meia largura
        $this->W = $wl;
        $yy = $y;
        $yy += $this->escreve($x, $yy + 0.5, $wl, [$this->cp(trim("Modal $modal de Carga"))], 11, 'B') + 1.5;
        $unid = $this->cUnid === '02' ? 'Ton' : 'Kg';
        $yy += $this->linhaCampos($x, $yy, [
            ['Qtd. CT-e', str_pad($this->qCTe !== '' ? $this->qCTe : $this->qCT, 3, '0', STR_PAD_LEFT), 1, 9, 'B', 'C'],
            ['Qtd. NF-e', str_pad($this->qNFe !== '' ? $this->qNFe : $this->qNF, 3, '0', STR_PAD_LEFT), 1, 9, 'B', 'C'],
            ['Qtd. MDF-e', str_pad($this->qMDFe, 3, '0', STR_PAD_LEFT), 1, 9, 'B', 'C'],
            ["Peso Total ($unid)", $this->numero($this->qCarga, 4), 1.4, 9, 'B', 'C'],
        ]);
        $yy += $this->linhaCampos($x, $yy, [
            ['Valor Total da Carga (R$)', $this->numero($this->vCarga), 1, 9, 'B', 'C'],
        ]);
        // protocolo de autorização (e as mensagens obrigatórias do MOC 2.4/2.5)
        $prot = [];
        if ($this->nProt !== '') {
            $prot[] = [$this->nProt . ' - ' . $this->dataHora($this->dhRecbto), 9, ''];
        }
        foreach ($this->situacao['protocolo'] as $t) {
            $prot[] = [$t, 8, 'B'];
        }
        $lsP = [];
        $hP = $this->lh(7) + 1;
        foreach ($prot as [$t, $s, $st]) {
            $ls = $this->quebra($this->cp($t), $wl, $s, $st);
            $lsP[] = [$ls, $s, $st];
            $hP += count($ls) * $this->lh($s);
        }
        $yP = $yy;
        $this->W = $W;
        // controle do fisco
        $wBar = min(88, $wr - 10);
        $hBar = 16;
        $chave = $this->chaveFormatada($this->chMDFe);
        $sizeCh = 9.5;
        $this->fonte($sizeCh);
        while ($this->pdf->GetStringWidth($chave) > $wr - 2 && $sizeCh > 7) {
            $sizeCh -= 0.5;
            $this->fonte($sizeCh);
        }
        $lsCh = $this->quebra($chave, $wr, $sizeCh);
        $lsUrl = $this->quebra($this->cp('Consulte em https://dfe-portal.svrs.rs.gov.br/MDFe/consulta'), $wr, 7.5, 'B');
        $hR = 1 + $this->lh(7) + 1 + $hBar + 2 + $this->lh(6.5) + count($lsCh) * $this->lh($sizeCh)
            + 1 + count($lsUrl) * $this->lh(7.5) + 1;
        $hL = ($yP - $y) + max($hP, 12);
        $hT = max($hL, $hR);
        // protocolo ocupa o que sobrar à esquerda
        $hP = $hT - ($yP - $y);
        $this->pdf->Rect($x, $yP, $wl, $hP);
        $ty = $yP + 0.3;
        $ty += $this->escreve($x, $ty, $wl, [$this->cp('Protocolo de Autorização de Uso')], 7, 'B');
        foreach ($lsP as [$ls, $s, $st]) {
            $ty += $this->escreve($x, $ty, $wl, $ls, $s, $st, 'C');
        }
        $this->pdf->RoundedRect($x, $y, $wl, $hT, 0.8, '1234', 'D');
        $this->pdf->RoundedRect($xr, $y, $wr, $hT, 0.8, '1234', 'D');
        $ry = $y + 1;
        $ry += $this->escreve($xr, $ry, $wr, [$this->cp('CONTROLE DO FISCO')], 7, 'B') + 1;
        $this->pdf->SetFillColor(0, 0, 0);
        $this->pdf->Code128($xr + ($wr - $wBar) / 2, $ry, $this->chMDFe, $wBar, $hBar);
        $this->pdf->SetFillColor(255, 255, 255);
        $ry += $hBar + 2;
        $ry += $this->escreve($xr, $ry, $wr, [$this->cp('Chave de Acesso')], 6.5, 'B');
        $ry += $this->escreve($xr, $ry, $wr, $lsCh, $sizeCh, '', 'C') + 1;
        $this->escreve($xr, $ry, $wr, $lsUrl, 7.5, 'B', 'C');
        $this->y = $y + $hT + 2;
    }

    private function modalRodoviario(): void
    {
        $rntrcEmit = '';
        foreach ($this->filhos($this->rodo, 'infANTT') as $antt) {
            $rntrcEmit = $this->tag($antt, 'RNTRC');
        }
        if ($rntrcEmit === '') {
            // MDF-e 1.00: RNTRC direto em <rodo>
            foreach ($this->filhos($this->rodo, 'RNTRC') as $n) {
                $rntrcEmit = trim($n->nodeValue);
            }
        }
        $veiculos = [];
        $lista = [];
        if ($this->veicTracao) {
            $lista[] = ['Tração', $this->veicTracao];
        }
        $i = 0;
        foreach ($this->veicReboque as $r) {
            $lista[] = ['Reboque ' . (++$i), $r];
        }
        foreach ($lista as [$tipo, $v]) {
            $prop = $this->filhos($v, 'prop')[0] ?? null;
            $rntrc = $prop ? $this->tag($prop, 'RNTRC') : $rntrcEmit;
            $dono = $prop ? trim($this->cnpjCpf($prop) . ' - ' . $this->tag($prop, 'xNome'), ' -') : '';
            $veiculos[] = [
                $tipo,
                $this->filho($v, 'placa'),
                $this->filho($v, 'UF'),
                $this->filho($v, 'RENAVAM'),
                $this->numero($this->filho($v, 'tara'), 0),
                $this->numero($this->filho($v, 'capKG'), 0),
                $rntrc,
                $dono,
            ];
        }
        $this->tabela('Veículos', [
            ['Veículo', 16, 'L'], ['Placa', 17, 'C'], ['UF', 8, 'C'], ['RENAVAM', 22, 'C'],
            ['Tara (kg)', 16, 'R'], ['Cap. (kg)', 16, 'R'], ['RNTRC', 18, 'C'], ['Proprietário', 83, 'L'],
        ], $veiculos);

        $condutores = [];
        foreach ($this->veicTracao ? $this->filhos($this->veicTracao, 'condutor') : [] as $c) {
            $condutores[] = [$this->documento($this->tag($c, 'CPF')), $this->tag($c, 'xNome')];
        }
        $this->tabela('Condutores', [['CPF', 32, 'C'], ['Nome', 164, 'L']], $condutores);

        $vales = [];
        // tpValePed: NT 2025.001 do MDF-e deixou válidos só 01 e 04 (02/03 em XML antigo)
        $tiposVale = ['01' => 'TAG', '02' => 'Cupom', '03' => 'Cartão', '04' => 'Leitura de placa'];
        foreach ($this->todos('disp', $this->rodo) as $d) {
            $pg = $this->tag($d, 'CNPJPg') ?: $this->tag($d, 'CPFPg');
            $tp = $this->tag($d, 'tpValePed');
            $vales[] = [
                $this->documento($this->tag($d, 'CNPJForn')),
                $this->documento($pg),
                $this->tag($d, 'nCompra'),
                trim($tp . (isset($tiposVale[$tp]) ? ' - ' . $tiposVale[$tp] : '')),
                $this->numero($this->tag($d, 'vValePed')),
            ];
        }
        $this->tabela('Vale-Pedágio', [
            ['Fornecedora CNPJ', 38, 'C'], ['Responsável CNPJ/CPF', 38, 'C'], ['Nº Comprovante', 62, 'L'],
            ['Tipo', 28, 'L'], ['Valor (R$)', 30, 'R'],
        ], $vales);

        $ciots = [];
        foreach ($this->todos('infCIOT', $this->rodo) as $c) {
            $ciots[] = [$this->tag($c, 'CIOT'), $this->cnpjCpf($c)];
        }
        if (!$ciots && $this->ciot !== '') {
            $ciots[] = [$this->ciot, ''];
        }
        $this->tabela('CIOT', [['CIOT', 98, 'L'], ['Responsável CNPJ/CPF', 98, 'L']], $ciots);

        $contratantes = [];
        foreach ($this->todos('infContratante', $this->rodo) as $c) {
            $contratantes[] = [$this->cnpjCpf($c), $this->tag($c, 'xNome')];
        }
        $this->tabela('Contratantes', [['CNPJ/CPF/Id. estrangeiro', 50, 'L'], ['Nome', 146, 'L']], $contratantes);
    }

    private function modalAereo(): void
    {
        $a = $this->aereo;
        $this->tabela('Aeronave e voo', [
            ['Nacionalidade', 26, 'C'], ['Matrícula', 30, 'C'], ['Nº do voo', 30, 'C'],
            ['Aeródromo de embarque', 38, 'C'], ['Aeródromo de destino', 38, 'C'], ['Data do voo', 34, 'C'],
        ], [[
            $this->tag($a, 'nac'), $this->tag($a, 'matr'), $this->tag($a, 'nVoo'),
            $this->tag($a, 'cAerEmb'), $this->tag($a, 'cAerDes'), $this->dataHora($this->tag($a, 'dVoo')),
        ]]);
    }

    private function modalAquaviario(): void
    {
        $a = $this->aquav;
        $this->tabela('Embarcação', [
            ['IRIN', 18, 'C'], ['MMSI', 18, 'C'], ['Tipo', 10, 'C'], ['Código', 18, 'C'], ['Nome', 44, 'L'],
            ['Nº viagem', 18, 'C'], ['Porto embarque', 22, 'C'], ['Porto destino', 22, 'C'], ['Porto transbordo', 22, 'C'],
        ], [[
            // MMSI: campo novo da NT 2025.001 do MDF-e (opcional)
            $this->tag($a, 'irin'), $this->tag($a, 'MMSI'), $this->tag($a, 'tpEmb'), $this->tag($a, 'cEmbar'),
            $this->tag($a, 'xEmbar'), $this->tag($a, 'nViag'), $this->tag($a, 'cPrtEmb'), $this->tag($a, 'cPrtDest'),
            $this->tag($a, 'prtTrans'),
        ]]);
        $pares = function (string $grupo, string $cod, string $nome) use ($a): array {
            $r = [];
            foreach ($this->todos($grupo, $a) as $g) {
                $r[] = [$this->tag($g, $cod), $this->tag($g, $nome)];
            }

            return $r;
        };
        $this->tabela('Terminais de carregamento', [['Código', 40, 'C'], ['Terminal', 156, 'L']],
            $pares('infTermCarreg', 'cTermCarreg', 'xTermCarreg'));
        $this->tabela('Terminais de descarregamento', [['Código', 40, 'C'], ['Terminal', 156, 'L']],
            $pares('infTermDescarreg', 'cTermDescarreg', 'xTermDescarreg'));
        $this->tabela('Embarcações do comboio', [['Código', 40, 'C'], ['Balsa', 156, 'L']],
            $pares('infEmbComb', 'cEmbComb', 'xBalsa'));
        $this->tabela('Unidades de carga vazias', [['Identificação', 120, 'L'], ['Tipo', 76, 'C']],
            $pares('infUnidCargaVazia', 'idUnidCargaVazia', 'tpUnidCargaVazia'));
        $this->tabela('Unidades de transporte vazias', [['Identificação', 120, 'L'], ['Tipo', 76, 'C']],
            $pares('infUnidTranspVazia', 'idUnidTranspVazia', 'tpUnidTranspVazia'));
    }

    private function modalFerroviario(): void
    {
        $f = $this->ferrov;
        $trem = $this->filhos($f, 'trem')[0] ?? null;
        $this->tabela('Trem', [
            ['Prefixo', 30, 'C'], ['Data/hora de liberação', 40, 'C'], ['Origem', 46, 'L'], ['Destino', 46, 'L'],
            ['Qtd. vagões', 34, 'C'],
        ], [[
            $this->tag($trem, 'xPref'), $this->dataHora($this->tag($trem, 'dhTrem')), $this->tag($trem, 'xOri'),
            $this->tag($trem, 'xDest'), $this->tag($trem, 'qVag'),
        ]]);
        $vag = [];
        foreach ($this->filhos($f, 'vag') as $v) {
            $vag[] = [
                $this->filho($v, 'serie'), $this->filho($v, 'nVag'), $this->filho($v, 'nSeq'), $this->filho($v, 'tpVag'),
                $this->numero($this->filho($v, 'pesoBC'), 3), $this->numero($this->filho($v, 'pesoR'), 3),
                $this->numero($this->filho($v, 'TU'), 3),
            ];
        }
        $this->tabela('Vagões', [
            ['Série', 24, 'C'], ['Número', 30, 'C'], ['Sequência', 22, 'C'], ['Tipo', 22, 'C'],
            ['Peso base cálculo (t)', 34, 'R'], ['Peso real (t)', 30, 'R'], ['Tonelada útil (t)', 34, 'R'],
        ], $vag);
    }

    /** Observação (infCpl) e informações de interesse do fisco (infAdFisco); continua na folha seguinte. */
    private function observacao(): void
    {
        $linhas = [[$this->infCpl !== '' ? $this->infCpl : ' ']];
        if ($this->infAdFisco !== '') {
            $linhas[] = ['Informações adicionais de interesse do Fisco: ' . $this->infAdFisco];
        }
        $this->tabela('Observação', [['', 196, 'L']], $linhas, 7.5);
    }

    /** Municípios de carregamento, percurso e início da viagem. */
    private function percurso(): void
    {
        $car = [];
        foreach ($this->todos('infMunCarrega', $this->ide) as $m) {
            $car[] = $this->tag($m, 'cMunCarrega') . ' - ' . $this->tag($m, 'xMunCarrega');
        }
        $per = [];
        foreach ($this->todos('infPercurso', $this->ide) as $p) {
            $per[] = $this->tag($p, 'UFPer');
        }
        $linhas = [];
        if ($car) {
            $linhas[] = ['Carregamento (' . count($car) . ')', implode('; ', $car)];
        }
        $linhas[] = ['Percurso', implode(' > ', array_merge([$this->UFIni], $per, [$this->UFFim]))];
        $ini = $this->tag($this->ide, 'dhIniViagem');
        if ($ini !== '') {
            $linhas[] = ['Início da viagem', $this->dataHora($ini)];
        }
        $this->tabela('Carregamento e percurso', [['', 36, 'L'], ['', 160, 'L']], $linhas);
    }

    private function produtoPredominante(): void
    {
        $p = $this->todos('prodPred')[0] ?? null;
        if (!$p) {
            return;
        }
        $tipos = [
            '01' => 'Granel sólido', '02' => 'Granel líquido', '03' => 'Frigorificada', '04' => 'Conteinerizada',
            '05' => 'Carga Geral', '06' => 'Neogranel', '07' => 'Perigosa (granel sólido)',
            '08' => 'Perigosa (granel líquido)', '09' => 'Perigosa (carga frigorificada)',
            '10' => 'Perigosa (conteinerizada)', '11' => 'Perigosa (carga geral)',
            '12' => 'Granel pressurizada', // NT 2025.001 do MDF-e
        ];
        $tp = $this->tag($p, 'tpCarga');
        $this->tabela('Produto predominante', [
            ['Tipo de carga', 44, 'L'], ['Produto', 100, 'L'], ['GTIN', 28, 'C'], ['NCM', 24, 'C'],
        ], [[
            trim($tp . (isset($tipos[$tp]) ? ' - ' . $tipos[$tp] : '')),
            $this->tag($p, 'xProd'), $this->tag($p, 'cEAN'), $this->tag($p, 'NCM'),
        ]]);
    }

    /** Seguro da carga: responsável, seguradora, apólice e todas as averbações. */
    private function seguro(): void
    {
        $resp = ['1' => 'Emitente do MDF-e', '2' => 'Contratante'];
        $linhas = [];
        foreach ($this->todos('seg') as $s) {
            $r = $this->filhos($s, 'infResp')[0] ?? null;
            $g = $this->filhos($s, 'infSeg')[0] ?? null;
            $rs = $this->tag($r, 'respSeg');
            $aver = [];
            foreach ($this->filhos($s, 'nAver') as $n) {
                $aver[] = trim($n->nodeValue);
            }
            $linhas[] = [
                trim(($resp[$rs] ?? $rs) . ' ' . $this->cnpjCpf($r)),
                trim($this->cnpjCpf($g) . ' - ' . $this->tag($g, 'xSeg'), ' -'),
                $this->tag($s, 'nApol'),
                (string)count($aver),
            ];
            // averbações (sem limite no schema) em linha de largura total, logo abaixo
            if ($aver) {
                $linhas[] = ['linha' => 'Averbações: ' . implode(', ', $aver)];
            }
        }
        $this->tabela('Seguro da carga', [
            ['Responsável', 50, 'L'], ['Seguradora', 76, 'L'], ['Nº da apólice', 46, 'L'], ['Averbações', 24, 'C'],
        ], $linhas);
    }

    private function lacres(): void
    {
        $l = [];
        foreach ($this->todos('nLacre') as $n) {
            $l[] = trim($n->nodeValue);
        }
        if ($l) {
            $this->tabela('Lacres', [['', 196, 'L']], [[count($l) . ': ' . implode(', ', $l)]]);
        }
    }

    /**
     * Relação de todos os documentos vinculados, por município de descarregamento,
     * com o total por tipo (MOC Anexo II, 2.1 e 2.4).
     */
    private function documentosVinculados(): void
    {
        $tipos = [
            'infCTe' => ['CT-e', 'chCTe'], 'infNFe' => ['NF-e', 'chNFe'], 'infMDFeTransp' => ['MDF-e', 'chMDFe'],
            'infCT' => ['CT', ''], 'infNF' => ['NF', ''],
        ];
        $linhas = [];
        $cont = [];
        $total = 0;
        foreach ($this->todos('infMunDescarga') as $m) {
            $docs = [];
            foreach ($m->childNodes as $d) {
                if (!$d instanceof \DOMElement || !isset($tipos[$d->localName])) {
                    continue;
                }
                [$rot, $tagCh] = $tipos[$d->localName];
                $docs[] = [$rot, $tagCh !== '' ? $this->descreveEletronico($d, $tagCh) : $this->descrevePapel($d)];
                $cont[$rot] = ($cont[$rot] ?? 0) + 1;
            }
            $total += count($docs);
            $linhas[] = ['grupo' => 'Descarregamento em ' . $this->tag($m, 'cMunDescarga') . ' - '
                . $this->tag($m, 'xMunDescarga') . ': ' . count($docs) . ' documento(s)'];
            for ($i = 0; $i < count($docs); $i += 2) {
                $linhas[] = [$docs[$i][0], $docs[$i][1], $docs[$i + 1][0] ?? '', $docs[$i + 1][1] ?? ''];
            }
        }
        if (!$linhas) {
            return;
        }
        $resumo = [];
        foreach ($cont as $rot => $n) {
            $resumo[] = "$rot: $n";
        }
        $this->tabela(
            'Documentos vinculados - total ' . $total . ($resumo ? ' (' . implode(', ', $resumo) . ')' : ''),
            [['Tipo', 12, 'C'], ['Chave de acesso / documento', 86, 'L'], ['Tipo', 12, 'C'],
                ['Chave de acesso / documento', 86, 'L']],
            $linhas,
            7.5
        );
    }

    private function descreveEletronico(\DOMElement $d, string $tagCh): string
    {
        $t = $this->chaveFormatada($this->tag($d, $tagCh));
        $extra = [];
        if ($this->tag($d, 'indReentrega') === '1') {
            $extra[] = 'Reentrega';
        }
        if (($s = $this->tag($d, 'SegCodBarra')) !== '') {
            $extra[] = 'Seg. cód. barras ' . $s;
        }
        foreach ($this->filhos($d, 'infUnidTransp') as $u) {
            $extra[] = 'Unid. transp. ' . trim($this->tag($u, 'tpUnidTransp') . ' ' . $this->tag($u, 'idUnidTransp'));
        }
        foreach ($this->filhos($d, 'peri') as $p) {
            $extra[] = 'Perigoso ONU ' . $this->tag($p, 'nONU');
        }
        if (($e = $this->filhos($d, 'infEntregaParcial')[0] ?? null) !== null) {
            $extra[] = 'Entrega parcial ' . $this->tag($e, 'qtdParcial') . '/' . $this->tag($e, 'qtdTotal');
        }

        return $t . ($extra ? "\n" . implode('; ', $extra) : '');
    }

    /** Documento em papel (MDF-e 1.00: infCT/infNF). */
    private function descrevePapel(\DOMElement $d): string
    {
        $n = $this->tag($d, 'nCT') ?: $this->tag($d, 'nNF');
        $v = $this->tag($d, 'vCarga') ?: $this->tag($d, 'vNF');
        $emit = $this->tag($d, 'CNPJ');

        return trim('Nº ' . $n . ' Série ' . $this->tag($d, 'serie')
            . ($emit !== '' ? ' - ' . $this->documento($emit) . ' ' . $this->tag($d, 'UF') : '')
            . ' - ' . $this->dataHora($this->tag($d, 'dEmi')) . ($v !== '' ? ' - R$ ' . $this->numero($v) : ''));
    }

    protected function pQRDAMDFE($xQr = 0, $yQr = 0)
    {
        $barcode = new Barcode();
        $bobj = $barcode->getBarcodeObj(
            'QRCODE,M',
            $this->qrCodMDFe,
            -4,
            -4,
            'black',
            array(-2, -2, -2, -2)
        )->setBackgroundColor('white');
        $qrcode = $bobj->getPngData();
        // prepare a base64 encoded "data url"
        $pic = 'data://text/plain;base64,' . base64_encode($qrcode);
        $this->pdf->image($pic, $xQr, $yQr, 30, 30, 'PNG');
    }

    /**
     * printMDFe
     *
     * @param string $nome
     * @param string $destino
     * @param string $printer
     * @return string
     */
    public function printMDFe($nome = '', $destino = 'I', $printer = '')
    {
        //monta
        $command = '';
        if ($nome == '') {
            $file = $this->pdfDir . 'mdfe.pdf';
        } else {
            $file = $this->pdfDir . $nome;
        }
        if ($destino != 'I' && $destino != 'S' && $destino != 'F') {
            $destino = 'I';
        }
        if ($printer != '') {
            $command = "-P $printer";
        }

        $this->buildMDFe();
        $arq = $this->pdf->Output($file, $destino);
        if ($destino == 'S' && $command != '') {
            //aqui pode entrar a rotina de impressão direta
            $command = "lpr $command $file";
            system($command, $retorno);
        }

        return $arq;
    }//fim printMDFe

    /**
     * Dados brutos do PDF
     * @return string
     */
    public function render()
    {
        return $this->pdf->getPdf();
    }
}
