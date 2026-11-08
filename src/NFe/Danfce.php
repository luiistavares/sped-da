<?php

namespace NFePHP\DA\NFe;

/**
 * Classe para a impressão em PDF do Documento Auxiliar de NFe Consumidor
 * NOTA: Esta classe não é a indicada para quem faz uso de impressoras térmicas ESCPOS
 *
 * @category  Library
 * @package   nfephp-org/sped-da
 * @copyright 2009-2016 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @link      http://github.com/nfephp-org/sped-da for the canonical source repository
 * @author    Roberto Spadim <roberto at spadim dot com dot br>
 */
use Exception;
use InvalidArgumentException;
use NFePHP\DA\Legacy\Dom;
use NFePHP\DA\Legacy\Pdf;
use NFePHP\DA\Legacy\Common;
use Com\Tecnick\Barcode\Barcode;
use DateTime;

class Danfce extends Common
{
    use \NFePHP\DA\Common\XmlSanitizerTrait;

    protected $papel;
    protected $xml; // string XML NFe
    protected $logomarca=''; // path para logomarca em jpg
    protected $formatoChave="#### #### #### #### #### #### #### #### #### #### ####";
    protected $debugMode=0; //ativa ou desativa o modo de debug
    protected $tpImp; //ambiente
    protected $fontePadrao='Times';
    protected $nfeProc;
    /*
     * PATCH 19 (fork): propriedades que eram criadas dinamicamente (deprecated no
     * PHP 8.2). Públicas, como as dinâmicas eram.
     */
    public $dom;
    public $orientacao;
    public $logoAlign;
    public $numero_registro_dpec;
    public $wPrint;
    public $hPrint;
    protected $nfe;
    protected $infNFe;
    protected $ide;
    protected $enderDest;
    protected $ICMSTot;
    protected $imposto;
    protected $emit;
    protected $enderEmit;
    protected $qrCode;
    protected $det;
    protected $infAdic;
    protected $textoAdic;
    protected $pag;
    protected $vTroco;
    protected $dest;
    protected $imgQRCode;
    protected $urlQR = '';
    protected $pdf;
    protected $margemInterna = 2;
    protected $hMaxLinha = 9;
    protected $hBoxLinha = 6;
    protected $hLinha = 3;
    /*
     * Retorna a sigla da UF
     * @var string
     */
    protected $UFSigla = [
        '12' => 'AC',
        '27' => 'AL',
        '13' => 'AM',
        '16' => 'AP',
        '29' => 'BA',
        '23' => 'CE',
        '53' => 'DF',
        '32' => 'ES',
        '52' => 'GO',
        '31' => 'MG',
        '50' => 'MS',
        '51' => 'MT',
        '21' => 'MA',
        '15' => 'PA',
        '25' => 'PB',
        '26' => 'PE',
        '22' => 'PI',
        '41' => 'PR',
        '33' => 'RJ',
        '11' => 'RO',
        '24' => 'RN',
        '14' => 'RR',
        '43' => 'RS',
        '42' => 'SC',
        '28' => 'SE',
        '35' => 'SP',
        '17' => 'TO'
    ];
    /*
     * Fonte: http://nfce.encat.org/consumidor/consulte-sua-nota/
     * URL referente a pagina de consulta da NFCe pela chave de acesso
     * @var string
     */
    protected $urlConsulta = [
        '1' => [
            'AC' => 'www.sefaznet.ac.gov.br/nfce/consulta',
            'AL' => 'http://nfce.sefaz.al.gov.br/consultaNFCe.htm',
            'AP' => 'https://www.sefaz.ap.gov.br/sate/seg/SEGf_AcessarFuncao.jsp?cdFuncao=FIS_1261',
            'AM' => 'sistemas.sefaz.am.gov.br/nfceweb/formConsulta.do',
            'BA' => 'nfe.sefaz.ba.gov.br/servicos/nfce/default.aspx',
            'CE' => '',
            'DF' => 'http://dec.fazenda.df.gov.br/NFCE/',
            'ES' => 'http://app.sefaz.es.gov.br/ConsultaNFCe',
            'GO' => '',
            'MA' => 'http://www.nfce.sefaz.ma.gov.br/portal/consultaNFe.do?method=preFilterCupom&',
            'MT' => 'http://www.sefaz.mt.gov.br/nfce/consultanfce',
            'MS' => 'http://www.dfe.ms.gov.br/nfce',
            'MG' => '',
            'PA' => 'https://appnfc.sefa.pa.gov.br/portal/view/consultas/nfce/consultanfce.seam',
            'PB' => 'www.receita.pb.gov.br/nfce',
            'PR' => 'http://www.fazenda.pr.gov.br',
            'PE' => '',
            'PI' => 'http://webas.sefaz.pi.gov.br/nfceweb/consultarNFCe.jsf',
            'RJ' => 'www.nfce.fazenda.rj.gov.br/consulta',
            'RN' => 'http://nfce.set.rn.gov.br/consultarNFCe.aspx',
            'RS' => 'https://www.sefaz.rs.gov.br/NFCE/NFCE-COM.aspx',
            'RO' => 'http://www.nfce.sefin.ro.gov.br',
            'RR' => 'https://www.sefaz.rr.gov.br/nfce/servlet/wp_consulta_nfce',
            'SC' => '',
            'SP' => 'https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaPublica.aspx',
            'SE' => 'http://www.nfce.se.gov.br/portal/portalNoticias.jsp',
            'TO' => ''
        ],
        '2' => [
            'AC' => 'http://hml.sefaznet.ac.gov.br/nfce/consulta',
            'AL' => 'http://nfce.sefaz.al.gov.br/consultaNFCe.htm',
            'AP' => 'https://www.sefaz.ap.gov.br/sate1/seg/SEGf_AcessarFuncao.jsp?cdFuncao=FIS_1261',
            'AM' => 'homnfce.sefaz.am.gov.br/nfceweb/formConsulta.do',
            'BA' => 'http://hnfe.sefaz.ba.gov.br/servicos/nfce/default.aspx',
            'CE' => 'http://nfceh.sefaz.ce.gov.br/pages/consultaNota.jsf',
            'DF' => 'http://dec.fazenda.df.gov.br/NFCE/',
            'ES' => 'http://homologacao.sefaz.es.gov.br/ConsultaNFCe',
            'GO' => '',
            'MA' => 'http://www.hom.nfce.sefaz.ma.gov.br/portal/consultarNFCe.jsp',
            'MT' => 'http://homologacao.sefaz.mt.gov.br/nfce/consultanfce',
            'MS' => 'http://www.dfe.ms.gov.br/nfce',
            'MG' => '',
            'PA' => 'https://appnfc.sefa.pa.gov.br/portal-homologacao/view/consultas/nfce/consultanfce.seam',
            'PB' => '',
            'PR' => 'http://www.fazenda.pr.gov.br',
            'PE' => '',
            'PI' => 'http://webas.sefaz.pi.gov.br/nfceweb-homologacao/consultarNFCe.jsf',
            'RJ' => 'www.nfce.fazenda.rj.gov.br/consulta',
            'RN' => 'http://nfce.set.rn.gov.br/consultarNFCe.aspx',
            'RS' => 'https://www.sefaz.rs.gov.br/NFCE/NFCE-COM.aspx',
            'RO' => 'http://www.nfce.sefin.ro.gov.br',
            'RR' => 'http://200.174.88.103:8080/nfce/servlet/wp_consulta_nfce',
            'SC' => '',
            'SP' => 'https://www.homologacao.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaPublica.aspx',
            'SE' => 'http://www.hom.nfe.se.gov.br/portal/portalNoticias.jsp',
            'TO' => ''
        ],
    ];
    /*
     * PATCH 13 (consultadanfe, 2026-11-08): leiaute dinâmico do DANFE NFC-e conforme o
     * Manual de Padrões DANFE NFC-e e QR Code v6.0 (mar/2025). Ver PATCHES.md.
     * Cada bloco mede o próprio texto antes de desenhar: nada sobrepõe, nada sai da
     * bobina e nada vira microtexto, com 1 ou 990 itens. O PDF é montado em 2 passos
     * (mede → desenha) para que cada página tenha a altura exata do conteúdo.
     */
    /** @var \DOMElement|null grupo infProt do protocolo de autorização */
    protected $infProt;
    protected $tpEmis = '1';
    protected $tpAmb = '1';
    protected $urlChave = '';
    protected $via = 'Via Consumidor';
    /** Contingência off-line sem protocolo: imprime também a "Via Estabelecimento" (manual 3.1.8). */
    protected $offlineDuasVias = true;
    /** @var array{0:string,1:string}|null título e subtítulo de NFC-e cancelada/denegada */
    protected $situacao = null;
    /** Limite de altura de uma página (mm). PDF aceita até 14.400 pt = 5.080 mm (ISO 32000-1, anexo C). */
    protected $limitePagina = 5000;
    protected $margem = 2;
    protected $yAtual = 0;
    protected $medindo = false;
    protected $alturasPaginas = [];
    protected $paginaAtual = 0;
    protected $pngQRCode = null;

    /**
     * __contruct
     *
     * @param string $docXML
     * @param string $sPathLogo
     * @param string $mododebug
     * @param string $idToken
     * @param string $Token
     */
    public function __construct(
        $docXML = '',
        $sPathLogo = '',
        $mododebug = 0,
        // habilita os erros do sistema
        $idToken = '',
        $emitToken = '',
        $urlQR = ''
    ) {
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
        $this->xml = $docXML;
        $this->logomarca = $sPathLogo;
        $this->fontePadrao = 'Times';
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
            $this->nfeProc    = $this->dom->getElementsByTagName("nfeProc")->item(0);
            $this->nfe        = $this->dom->getElementsByTagName("NFe")->item(0);
            $this->infNFe     = $this->dom->getElementsByTagName("infNFe")->item(0);
            $this->ide        = $this->dom->getElementsByTagName("ide")->item(0);
            $this->emit       = $this->dom->getElementsByTagName("emit")->item(0);
            $this->enderEmit  = $this->dom->getElementsByTagName("enderEmit")->item(0);
            $this->det        = $this->dom->getElementsByTagName("det");
            $this->dest       = $this->dom->getElementsByTagName("dest")->item(0);
            $this->imposto    = $this->dom->getElementsByTagName("imposto")->item(0);
            $this->ICMSTot    = $this->dom->getElementsByTagName("ICMSTot")->item(0);
            $this->tpImp      = $this->getTagValue($this->ide, "tpImp");
            $this->infAdic    = $this->dom->getElementsByTagName("infAdic")->item(0);
            $this->infProt    = $this->dom->getElementsByTagName("infProt")->item(0);
            $this->tpEmis     = $this->getTagValue($this->ide, "tpEmis") ?: '1';
            $this->tpAmb      = $this->getTagValue($this->ide, "tpAmb") ?: '1';
            $this->urlChave   = $this->getTagValue($this->dom->documentElement, "urlChave");
            //se for o layout 4.0 busca pelas tags de detalhe do pagamento
            //senao, busca pelas tags de pagamento principal
            if ($this->infNFe->getAttribute("versao") == "4.00") {
                $this->pag = $this->dom->getElementsByTagName("detPag");
                $tagPag = $this->dom->getElementsByTagName("pag")->item(0);
                $this->vTroco = $this->getTagValue($tagPag, "vTroco");
            } else {
                $this->pag = $this->dom->getElementsByTagName("pag");
            }
            $qr = $this->dom->getElementsByTagName('qrCode')->item(0);
            $this->qrCode = $qr ? trim($qr->nodeValue) : '';
        }
        if ($this->getTagValue($this->ide, "mod") != '65') {
            throw new InvalidArgumentException("O xml do DANFE deve ser uma NFC-e modelo 65");
        }
        $this->situacao = $this->situacaoNota();
    }

    public function getPapel()
    {
        return $this->papel;
    }

    public function setPapel($aPap)
    {
        $this->papel = $aPap;
    }

    /**
     * Liga/desliga a 2ª via ("Via Estabelecimento") da contingência off-line sem protocolo.
     * O manual permite que a UF dispense a 2ª via (item 3.1.8).
     */
    public function setOffLineDoublePrint($flag = true)
    {
        $this->offlineDuasVias = (bool) $flag;
    }

    public function monta(
        $orientacao = 'P',
        $papel = '',
        $logoAlign = 'C',
        $classPdf = false,
        $depecNumReg = ''
    ) {
        return $this->montaDANFE($orientacao, $papel, $logoAlign, $classPdf, $depecNumReg);
    }

    public function montaDANFE(
        $orientacao = 'P',
        $papel = '',
        $logoAlign = 'C',
        $classPdf = false,
        $depecNumReg = ''
    ) {
        $this->orientacao = 'P';
        $this->logoAlign = $logoAlign;
        $this->numero_registro_dpec = $depecNumReg;
        $this->wPrint = 80 - 2 * $this->margem;
        $this->textoAdic = $this->textoInfCpl();
        $vias = ['Via Consumidor'];
        if ($this->tpEmis == '9' && empty($this->infProt) && $this->situacao === null && $this->offlineDuasVias) {
            $vias[] = 'Via Estabelecimento';
        }
        // passo 1: mede (sem desenhar) para saber a altura de cada página
        $this->medindo = true;
        $this->alturasPaginas = [];
        $this->pdf = new Pdf('P', 'mm', [80, $this->limitePagina]);
        $this->desenhaVias($vias);
        // passo 2: desenha, cada página com a altura medida
        $this->medindo = false;
        $this->pdf = $classPdf ? $classPdf : new Pdf('P', 'mm', [80, $this->alturasPaginas[0]]);
        $this->pdf->aliasNbPages();
        $this->pdf->setDrawColor(0, 0, 0);
        $this->pdf->setFillColor(255, 255, 255);
        $this->pdf->open();
        $this->desenhaVias($vias);
        $this->papel = [80, $this->alturasPaginas[0]];
        $this->hPrint = $this->alturasPaginas[0] - 2 * $this->margem;
        $id = str_replace('NFe', '', $this->infNFe->getAttribute("Id"));
        if ($classPdf !== false) {
            return ['id' => $id, 'classe_PDF' => $this->pdf];
        }
        return $id;
    }

    /** Desenha o documento uma vez por via, cada via começando em página nova. */
    protected function desenhaVias(array $vias)
    {
        $this->paginaAtual = 0;
        foreach ($vias as $via) {
            $this->via = $via;
            $this->novaPagina();
            $this->blocoEmitente();        // Divisão I
            $this->blocoSituacao();        // cancelada / denegada / contingência
            $this->blocoItens();           // Divisão II
            $this->blocoTotais();          // Divisão III
            $this->blocoMensagemFiscal();  // Divisão VIII
            $this->blocoConsultaChave();   // Divisão IV
            $this->blocoConsumidor();      // Divisão VI
            $this->blocoIdentificacao();   // Divisão VII
            $this->blocoQRCode();          // Divisão V
            $this->blocoInfContribuinte(); // Divisão IX
        }
        $this->fechaPagina();
    }

    protected function novaPagina()
    {
        if ($this->paginaAtual > 0) {
            $this->fechaPagina();
        }
        $this->paginaAtual++;
        $altura = $this->medindo ? $this->limitePagina : $this->alturasPaginas[$this->paginaAtual - 1];
        $this->pdf->addPage('P', [80, $altura]);
        $this->pdf->setMargins($this->margem, $this->margem);
        $this->pdf->setLineWidth(0.1);
        $this->pdf->setTextColor(0, 0, 0);
        $this->yAtual = $this->margem;
    }

    protected function fechaPagina()
    {
        if ($this->medindo) {
            $this->alturasPaginas[$this->paginaAtual - 1] = ceil($this->yAtual + $this->margem + 1);
        }
    }

    /** Quebra a página se o próximo trecho (altura $h, em mm) não couber. */
    protected function garante($h)
    {
        if ($this->yAtual + $h > $this->limitePagina - $this->margem && $this->yAtual > $this->margem + 1) {
            $this->novaPagina();
            return true;
        }
        return false;
    }

    /** Texto como o pTextBox o desenha (entidades decodificadas, Windows-1252). */
    protected static function paraFonte($texto)
    {
        $texto = trim(html_entity_decode((string) $texto, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    }

    /** Altura (mm) do texto quebrado na largura $w com a fonte $aFont — a mesma quebra do pTextBox. */
    protected function alturaTexto($texto, $w, $aFont)
    {
        $t = self::paraFonte($texto);
        if ($t === '') {
            return 0;
        }
        $this->pdf->setFont($aFont['font'], $aFont['style'], $aFont['size']);
        $n = $this->pdf->wordWrap($t, $w);
        return $n * $this->pdf->fontSize;
    }

    protected function fonte($size = 7, $style = '')
    {
        return ['font' => $this->fontePadrao, 'size' => $size, 'style' => $style];
    }

    /**
     * Desenha uma linha com uma ou mais colunas, na altura da coluna mais alta.
     * Cada coluna: [x relativo à margem, largura, texto, aFont, alinhamento].
     * A largura de quebra desconta 0,6 mm (o pTextBox desenha 'L' em x + 0,5).
     */
    protected function linha(array $colunas, $espacoDepois = 0.3)
    {
        $alturas = [];
        $h = 0;
        foreach ($colunas as $k => $c) {
            $alturas[$k] = $this->alturaTexto($c[2], $c[1] - 0.6, $c[3]);
            $h = max($h, $alturas[$k]);
        }
        if ($h <= 0) {
            return 0;
        }
        $this->garante($h);
        if (!$this->medindo) {
            foreach ($colunas as $k => $c) {
                if ($alturas[$k] > 0) {
                    $this->pTextBox(
                        $this->margem + $c[0],
                        $this->yAtual,
                        $c[1] - 0.6,
                        $alturas[$k],
                        $c[2],
                        $c[3],
                        'T',
                        $c[4],
                        0,
                        '',
                        false
                    );
                }
            }
        }
        $this->yAtual += $h + $espacoDepois;
        return $h;
    }

    /** Texto em uma coluna só, na largura útil inteira. */
    protected function texto($texto, $size = 7, $style = '', $align = 'C', $espacoDepois = 0.3)
    {
        return $this->linha([[0, $this->wPrint, $texto, $this->fonte($size, $style), $align]], $espacoDepois);
    }

    /** Linha separadora entre divisões. */
    protected function separador()
    {
        $this->garante(2);
        if (!$this->medindo) {
            $y = $this->yAtual + 0.9;
            $this->pdf->line($this->margem, $y, $this->margem + $this->wPrint, $y);
        }
        $this->yAtual += 1.8;
    }

    /**
     * Número do XML formatado com vírgula decimal e ponto de milhar, SEM passar por
     * float (vUnCom tem até 21 dígitos significativos; float perderia os últimos).
     * Mantém todas as casas decimais significativas (mínimo $minDec).
     */
    protected static function numero($valor, $minDec = 2)
    {
        $v = trim((string) $valor);
        if ($v === '') {
            return '';
        }
        $sinal = '';
        if ($v[0] === '-' || $v[0] === '+') {
            $sinal = $v[0] === '-' ? '-' : '';
            $v = substr($v, 1);
        }
        if (!preg_match('/^\d*(\.\d*)?$/', $v)) {
            return trim((string) $valor); // não numérico: imprime como veio
        }
        $partes = explode('.', $v, 2);
        $inteiro = ltrim($partes[0], '0');
        $inteiro = $inteiro === '' ? '0' : $inteiro;
        $dec = str_pad(rtrim($partes[1] ?? '', '0'), $minDec, '0');
        $inteiro = strrev(implode('.', str_split(strrev($inteiro), 3)));
        return $sinal . $inteiro . ($dec !== '' ? ',' . $dec : '');
    }

    protected static function dataHora($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }
        try {
            // o XML traz o fuso do emitente: o formato mantém a hora local dele
            return (new DateTime($valor))->format('d/m/Y H:i:s');
        } catch (\Exception $e) {
            return $valor;
        }
    }

    protected function documento($valor)
    {
        $d = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $valor));
        if (strlen($d) === 14) {
            return $this->pFormat($d, '##.###.###/####-##');
        }
        if (strlen($d) === 11) {
            return $this->pFormat($d, '###.###.###-##');
        }
        return (string) $valor;
    }

    protected static function telefone($fone)
    {
        $f = preg_replace('/\D/', '', (string) $fone);
        if (strlen($f) === 11) {
            return '(' . substr($f, 0, 2) . ') ' . substr($f, 2, 5) . '-' . substr($f, 7);
        }
        if (strlen($f) === 10) {
            return '(' . substr($f, 0, 2) . ') ' . substr($f, 2, 4) . '-' . substr($f, 6);
        }
        return (string) $fone;
    }

    protected function endereco($ender)
    {
        if (empty($ender)) {
            return '';
        }
        $lgr = $this->getTagValue($ender, 'xLgr');
        $nro = $this->getTagValue($ender, 'nro');
        $cpl = $this->getTagValue($ender, 'xCpl');
        $bairro = $this->getTagValue($ender, 'xBairro');
        $mun = $this->getTagValue($ender, 'xMun');
        $uf = $this->getTagValue($ender, 'UF');
        $cep = preg_replace('/\D/', '', $this->getTagValue($ender, 'CEP'));
        $partes = [];
        $rua = trim($lgr . ($nro !== '' ? ', ' . $nro : ''));
        if ($rua !== '') {
            $partes[] = $rua;
        }
        foreach ([$cpl, $bairro] as $p) {
            if ($p !== '') {
                $partes[] = $p;
            }
        }
        $cidade = trim($mun . ($uf !== '' ? '/' . $uf : ''), '/');
        if ($cidade !== '') {
            $partes[] = $cidade;
        }
        if (strlen($cep) === 8) {
            $partes[] = 'CEP ' . substr($cep, 0, 5) . '-' . substr($cep, 5);
        }
        return implode(' - ', $partes);
    }

    /** Divisão I — emitente (CNPJ ou CPF, nome, endereço) e título do documento. */
    protected function blocoEmitente()
    {
        $nome = $this->getTagValue($this->emit, 'xNome');
        $cnpj = $this->getTagValue($this->emit, 'CNPJ');
        $cpf = $this->getTagValue($this->emit, 'CPF');
        $doc = $cnpj !== '' ? 'CNPJ: ' . $this->documento($cnpj) : ($cpf !== '' ? 'CPF: ' . $this->documento($cpf) : '');
        $ie = $this->getTagValue($this->emit, 'IE');
        $im = $this->getTagValue($this->emit, 'IM');
        $linhaDoc = trim($doc . ($ie !== '' ? '  IE: ' . $ie : '') . ($im !== '' ? '  IM: ' . $im : ''));
        $ender = $this->endereco($this->enderEmit);
        $fone = $this->getTagValue($this->enderEmit, 'fone');
        if ($fone !== '') {
            $ender .= ' - Fone: ' . self::telefone($fone);
        }
        $xTexto = 0;
        $wTexto = $this->wPrint;
        $yIni = $this->yAtual;
        $temLogo = is_string($this->logomarca) && $this->logomarca !== '' && is_file($this->logomarca);
        if ($temLogo) {
            $xTexto = 20;
            $wTexto = $this->wPrint - 20;
        }
        $this->linha([[$xTexto, $wTexto, $nome, $this->fonte(8, 'B'), $temLogo ? 'L' : 'C']]);
        $this->linha([[$xTexto, $wTexto, $linhaDoc, $this->fonte(7), $temLogo ? 'L' : 'C']]);
        $this->linha([[$xTexto, $wTexto, $ender, $this->fonte(7), $temLogo ? 'L' : 'C']]);
        if ($temLogo) {
            if (!$this->medindo) {
                // logo dentro de 18 x 18 mm, sem distorcer; imagem que o FPDF não lê
                // (ex.: PNG com canal alfa) fica de fora em vez de derrubar o PDF
                $dim = @getimagesize($this->logomarca);
                $wl = 18;
                $hl = 18;
                if (!empty($dim[0]) && !empty($dim[1])) {
                    $esc = min(18 / $dim[0], 18 / $dim[1]);
                    $wl = $dim[0] * $esc;
                    $hl = $dim[1] * $esc;
                }
                try {
                    $this->pdf->image($this->logomarca, $this->margem + (18 - $wl) / 2, $yIni + (18 - $hl) / 2, $wl, $hl);
                } catch (\Exception $e) {
                    // sem logo
                }
            }
            $this->yAtual = max($this->yAtual, $yIni + 18.5);
        }
        $this->yAtual += 0.8;
        $this->texto('DANFE NFC-e - Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica', 7.5, 'B');
        $this->texto('NFC-e não permite aproveitamento de crédito de ICMS', 6.5);
        $this->separador();
    }

    /** Cancelada/denegada (logo abaixo do cabeçalho) e aviso de contingência (manual 3.1.8). */
    protected function blocoSituacao()
    {
        $algo = false;
        if ($this->situacao !== null) {
            $this->texto($this->situacao[0], 12, 'B', 'C', 0.5);
            $this->texto($this->situacao[1], 7);
            $algo = true;
        }
        if ($this->tpEmis == '9') {
            $this->textoContingencia();
            $algo = true;
        }
        if ($algo) {
            $this->separador();
        }
    }

    protected function textoContingencia()
    {
        $this->texto('EMITIDA EM CONTINGÊNCIA', 9, 'B', 'C', 0.2);
        if (empty($this->infProt)) {
            $this->texto('Pendente de autorização', 8, 'I');
        }
    }

    /** Divisão II — itens: código, descrição, qtde, un, valor unitário e total (+ infAdProd). */
    protected function blocoItens()
    {
        $w = $this->wPrint;
        $wTot = 24;
        $cabecalho = function () use ($w, $wTot) {
            $this->texto('CÓDIGO  DESCRIÇÃO', 7, 'B', 'L', 0);
            $this->linha([
                [3, $w - $wTot - 3, 'QTDE  UN  x  VL UNIT R$', $this->fonte(7, 'B'), 'L'],
                [$w - $wTot, $wTot, 'VL TOTAL R$', $this->fonte(7, 'B'), 'R'],
            ], 0.6);
        };
        $cabecalho();
        $fItem = $this->fonte(7);
        $fInf = $this->fonte(6);
        foreach ($this->det as $det) {
            $prod = $det->getElementsByTagName('prod')->item(0);
            $desc = trim($this->getTagValue($prod, 'cProd') . '  ' . $this->getTagValue($prod, 'xProd'));
            $qtd = self::numero($this->getTagValue($prod, 'qCom'), 2) . '  ' . $this->getTagValue($prod, 'uCom')
                . '  x  ' . self::numero($this->getTagValue($prod, 'vUnCom'), 2);
            $tot = self::numero($this->getTagValue($prod, 'vProd'), 2);
            $infAdProd = '';
            foreach ($det->childNodes as $filho) {
                if ($filho->nodeName === 'infAdProd') {
                    $infAdProd = trim($filho->nodeValue);
                }
            }
            // o item inteiro na mesma página
            $hItem = $this->alturaTexto($desc, $w - 0.6, $fItem)
                + max($this->alturaTexto($qtd, $w - $wTot - 3.6, $fItem), $this->alturaTexto($tot, $wTot - 0.6, $fItem))
                + ($infAdProd !== '' ? $this->alturaTexto($infAdProd, $w - 3.6, $fInf) : 0) + 1.5;
            if ($this->garante($hItem)) {
                $cabecalho();
            }
            $this->linha([[0, $w, $desc, $fItem, 'L']], 0);
            $this->linha([
                [3, $w - $wTot - 3, $qtd, $fItem, 'L'],
                [$w - $wTot, $wTot, $tot, $fItem, 'R'],
            ], 0.2);
            if ($infAdProd !== '') {
                $this->linha([[3, $w - 3, $infAdProd, $fInf, 'L']], 0.2);
            }
            $this->yAtual += 0.5;
        }
        $this->separador();
    }

    /** Divisão III — totais, acréscimos/desconto (só se houver), pagamentos e troco. */
    protected function blocoTotais()
    {
        $w = $this->wPrint;
        $wVal = 30;
        $f = $this->fonte(7);
        $fB = $this->fonte(7, 'B');
        $par = function ($rotulo, $valor, $fonte) use ($w, $wVal) {
            $this->linha([[0, $w - $wVal, $rotulo, $fonte, 'L'], [$w - $wVal, $wVal, $valor, $fonte, 'R']], 0.2);
        };
        $v = function ($tag) {
            return $this->getTagValue($this->ICMSTot, $tag);
        };
        $par('Qtde. total de itens', (string) $this->det->length, $f);
        $par('Valor total R$', self::numero($v('vProd')), $f);
        $ajuste = false;
        foreach (['vDesc' => 'Desconto R$', 'vFrete' => 'Frete R$', 'vSeg' => 'Seguro R$', 'vOutro' => 'Outras despesas R$'] as $tag => $rotulo) {
            if ((float) $v($tag) > 0) {
                $par($rotulo, ($tag === 'vDesc' ? '-' : '') . self::numero($v($tag)), $f);
                $ajuste = true;
            }
        }
        if ($ajuste) {
            $par('Valor a pagar R$', self::numero($v('vNF')), $fB);
        }
        $this->yAtual += 0.8;
        $par('FORMA DE PAGAMENTO', 'VALOR PAGO R$', $fB);
        foreach ($this->pag as $pag) {
            $nome = $this->tipoPag($this->getTagValue($pag, 'tPag'));
            $xPag = $this->getTagValue($pag, 'xPag');
            if ($xPag !== '') {
                $nome .= ' - ' . $xPag;
            }
            $par($nome, self::numero($this->getTagValue($pag, 'vPag')), $f);
        }
        if ($this->vTroco !== null && $this->vTroco !== '') {
            $par('Troco R$', self::numero($this->vTroco), $f);
        }
        $vTotTrib = $v('vTotTrib');
        if ((float) $vTotTrib > 0) {
            $this->yAtual += 0.8;
            $par('Informação dos Tributos Totais Incidentes (Lei Federal 12.741/2012) R$', self::numero($vTotTrib), $f);
        }
        $this->separador();
    }

    /**
     * Divisão VIII — Área de Mensagem Fiscal: homologação (obrigatório), infAdFisco e
     * xMsg do protocolo (manual 3.1.8). Só aparece se houver o que mostrar.
     */
    protected function blocoMensagemFiscal()
    {
        $msgs = [];
        if ($this->tpAmb == '2') {
            $msgs[] = ['EMITIDA EM AMBIENTE DE HOMOLOGAÇÃO – SEM VALOR FISCAL', 8, 'B'];
        }
        $fisco = $this->getTagValue($this->infAdic, 'infAdFisco');
        if ($fisco !== '') {
            $msgs[] = [$fisco, 7, ''];
        }
        $xMsg = $this->getTagValue($this->infProt, 'xMsg');
        if ($xMsg !== '') {
            $msgs[] = [$xMsg, 7, ''];
        }
        if (empty($msgs)) {
            return;
        }
        $this->texto('ÁREA DE MENSAGEM FISCAL', 7, 'B');
        foreach ($msgs as $m) {
            $this->texto($m[0], $m[1], $m[2]);
        }
        $this->separador();
    }

    /** Divisão IV — URL de consulta (tag urlChave do XML) e chave em 11 blocos de 4. */
    protected function blocoConsultaChave()
    {
        $url = $this->urlChave;
        if ($url === '') {
            $cUF = $this->getTagValue($this->ide, 'cUF');
            $uf = $this->UFSigla[$cUF] ?? '';
            $url = $this->urlConsulta[$this->tpAmb][$uf] ?? '';
        }
        $this->texto('Consulte pela Chave de Acesso em', 7, 'B', 'C', 0);
        if ($url !== '') {
            $this->texto($url, 7);
        }
        $chave = str_replace('NFe', '', $this->infNFe->getAttribute('Id'));
        $this->texto(trim(implode(' ', str_split($chave, 4))), 8, 'B', 'C', 0.5);
        $this->separador();
    }

    /** Divisão VI — consumidor (CNPJ, CPF ou Id. Estrangeiro, nome e endereço) ou não identificado. */
    protected function blocoConsumidor()
    {
        $doc = '';
        $nome = '';
        $ender = '';
        if (!empty($this->dest)) {
            $cnpj = $this->getTagValue($this->dest, 'CNPJ');
            $cpf = $this->getTagValue($this->dest, 'CPF');
            $estr = $this->getTagValue($this->dest, 'idEstrangeiro');
            if ($cnpj !== '') {
                $doc = 'CONSUMIDOR CNPJ: ' . $this->documento($cnpj);
            } elseif ($cpf !== '') {
                $doc = 'CONSUMIDOR CPF: ' . $this->documento($cpf);
            } elseif ($estr !== '') {
                $doc = 'CONSUMIDOR Id. Estrangeiro: ' . $estr;
            }
            $nome = $this->getTagValue($this->dest, 'xNome');
            $ender = $this->endereco($this->dest->getElementsByTagName('enderDest')->item(0));
        }
        if ($doc === '' && $nome === '' && $ender === '') {
            $this->texto('CONSUMIDOR NÃO IDENTIFICADO', 7.5, 'B');
        } else {
            $this->texto($doc !== '' ? $doc : 'CONSUMIDOR', 7.5, 'B');
            $this->texto($nome, 7);
            $this->texto($ender, 7);
        }
        $this->separador();
    }

    /** Divisão VII — número, série, emissão, via e protocolo de autorização (suprimido sem protocolo). */
    protected function blocoIdentificacao()
    {
        $nNF = $this->getTagValue($this->ide, 'nNF');
        $serie = str_pad($this->getTagValue($this->ide, 'serie'), 3, '0', STR_PAD_LEFT);
        $emissao = self::dataHora($this->getTagValue($this->ide, 'dhEmi'));
        $this->texto("NFC-e nº $nNF  Série $serie  $emissao", 8, 'B', 'C', 0.2);
        $this->texto($this->via, 8, 'B');
        $nProt = $this->getTagValue($this->infProt, 'nProt');
        if ($nProt !== '') {
            $this->texto('Protocolo de autorização: ' . $nProt, 7.5, '', 'C', 0.2);
            $dh = self::dataHora($this->getTagValue($this->infProt, 'dhRecbto'));
            if ($dh !== '') {
                $this->texto('Data de autorização: ' . $dh, 7.5);
            }
        }
        if ($this->tpEmis == '9') {
            $this->yAtual += 0.5;
            $this->textoContingencia();
        }
    }

    /**
     * Divisão V — QR Code (conteúdo exato da tag qrCode, 50 x 50 mm, mínimo do manual 25 mm).
     *
     * XML sem infNFeSupl/qrCode (ou com a tag vazia): o DANFE sai sem QR Code em vez de
     * falhar inteiro (o tc-lib-barcode lança "Empty input" com conteúdo vazio). O QR não
     * pode ser montado a partir da chave: a versão 2 exige o hash com o CSC do contribuinte
     * e a versão 3 a assinatura dos parâmetros (manual v6.0, itens 4.3 e 4.4) — dados que
     * não estão no XML. A consulta continua possível pela chave (Divisão IV).
     */
    protected function blocoQRCode()
    {
        if ($this->pngQRCode === null) {
            // gerado no 1º passo (medição), para os dois passos decidirem igual
            $this->pngQRCode = false;
            if ((string) $this->qrCode !== '') {
                try {
                    $barcode = new Barcode();
                    $bobj = $barcode->getBarcodeObj(
                        'QRCODE,M',
                        $this->qrCode,
                        -4,
                        -4,
                        'black',
                        array(-2, -2, -2, -2)
                    )->setBackgroundColor('white');
                    $this->pngQRCode = $bobj->getPngData();
                } catch (\Exception $e) {
                    $this->pngQRCode = false; // conteúdo que não cabe num QR Code: sem imagem
                }
            }
        }
        if ($this->pngQRCode === false) {
            return;
        }
        $lado = 50;
        $this->yAtual += 1;
        $this->garante($lado + 1);
        if (!$this->medindo) {
            $pic = 'data://text/plain;base64,' . base64_encode($this->pngQRCode);
            $this->pdf->image($pic, (80 - $lado) / 2, $this->yAtual, $lado, $lado, 'PNG');
        }
        $this->yAtual += $lado + 1;
    }

    /** Divisão IX — mensagem de interesse do contribuinte (infCpl). */
    protected function blocoInfContribuinte()
    {
        if ($this->textoAdic === '') {
            return;
        }
        $this->separador();
        $this->texto('INFORMAÇÕES DE INTERESSE DO CONTRIBUINTE', 7, 'B');
        $this->texto($this->textoAdic, 7, '', 'L');
    }

    protected function textoInfCpl()
    {
        $infCpl = $this->getTagValue($this->infAdic, 'infCpl');
        if ($infCpl === '') {
            return '';
        }
        // ";" é a quebra de linha convencional do infCpl (mesma regra do DANFE A4)
        return str_replace(';', "\n", trim($this->pAnfavea($infCpl)));
    }

    /**
     * Cancelada (protocolo 101/135/151/155 ou evento 110111/110112 homologado no
     * nfeProc) ou denegada (110/301/302/303).
     *
     * @return array{0:string,1:string}|null
     */
    protected function situacaoNota()
    {
        $cStat = $this->getTagValue($this->infProt, 'cStat');
        if (in_array($cStat, ['101', '135', '151', '155'], true)) {
            return ['NFC-e CANCELADA', ''];
        }
        if (in_array($cStat, ['110', '301', '302', '303'], true)) {
            return ['NFC-e DENEGADA', 'Uso denegado (cStat ' . $cStat . ')'];
        }
        if (empty($this->dom)) {
            return null;
        }
        $chave = str_replace('NFe', '', $this->infNFe->getAttribute('Id'));
        foreach ($this->dom->getElementsByTagName('retEvento') as $ret) {
            $inf = $ret->getElementsByTagName('infEvento')->item(0);
            $tp = $this->getTagValue($inf, 'tpEvento');
            $st = $this->getTagValue($inf, 'cStat');
            $ch = $this->getTagValue($inf, 'chNFe');
            if (in_array($tp, ['110111', '110112'], true) && in_array($st, ['101', '135', '151', '155'], true)
                && ($ch === '' || $ch === $chave)
            ) {
                $sub = trim('Protocolo de cancelamento: ' . $this->getTagValue($inf, 'nProt') . '  '
                    . self::dataHora($this->getTagValue($inf, 'dhRegEvento')));
                return ['NFC-e CANCELADA', $sub];
            }
        }
        return null;
    }

    /** Nome do meio de pagamento (tPag, MOC 4.00 + NT 2020.006/2023.004); código desconhecido sai como código. */
    protected function tipoPag($tPag)
    {
        $nomes = [
            '01' => 'Dinheiro',
            '02' => 'Cheque',
            '03' => 'Cartão de Crédito',
            '04' => 'Cartão de Débito',
            '05' => 'Crédito Loja',
            '10' => 'Vale Alimentação',
            '11' => 'Vale Refeição',
            '12' => 'Vale Presente',
            '13' => 'Vale Combustível',
            '14' => 'Duplicata Mercantil',
            '15' => 'Boleto Bancário',
            '16' => 'Depósito Bancário',
            '17' => 'Pagamento Instantâneo (PIX)',
            '18' => 'Transferência bancária, Carteira Digital',
            '19' => 'Programa de fidelidade, Cashback, Crédito Virtual',
            '20' => 'Pagamento Instantâneo (PIX) - Estático',
            '21' => 'Crédito em Loja',
            '22' => 'Pagamento Eletrônico não Informado',
            '90' => 'Sem Pagamento',
            '99' => 'Outros',
        ];
        $tPag = trim((string) $tPag);
        return $nomes[$tPag] ?? ('Pagamento (código ' . $tPag . ')');
    }

    /**
     * printDANFE
     * Esta função envia a DANFE em PDF criada para o dispositivo informado.
     * O destino da impressão pode ser :
     * I-browser
     * D-browser com download
     * F-salva em um arquivo local com o nome informado
     * S-retorna o documento como uma string e o nome é ignorado.
     * Para enviar o pdf diretamente para uma impressora indique o
     * nome da impressora e o destino deve ser 'S'.
     *
     * @param  string $nome    Path completo com o nome do arquivo pdf
     * @param  string $destino Direção do envio do PDF
     * @param  string $printer Identificação da impressora no sistema
     * @return string Caso o destino seja S o pdf é retornado como uma string
     * @todo   Rotina de impressão direta do arquivo pdf criado
     */
    public function printDANFE($nome = '', $destino = 'I', $printer = '')
    {
        $arq = $this->pdf->Output($nome, $destino);
        if ($destino == 'S') {
            //aqui pode entrar a rotina de impressão direta
        }
        return $arq;
    }
    /**
     * Dados brutos do PDF
     * @return string
     */
    public function render()
    {
        return $this->pdf->getPdf();
    }

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
        // PATCH 13 (= Patch 11 do Danfe.php): só é Anfavea se tiver as tags dela com
        // atributos. Antes, qualquer "<" no infCpl ("<br>", "PRECO < 10", URL entre
        // "<>") fazia descartar tudo do primeiro "<" ao último ">".
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
     * str2Hex
     * Converte string para haxadecimal ASCII
     *
     * @param  string $str
     * @return string
     */
    protected static function str2Hex($str)
    {
        if ($str == '') {
            return '';
        }
        $hex = "";
        $iCount = 0;
        do {
            $hex .= sprintf("%02x", ord($str[$iCount]));
            $iCount++;
        } while ($iCount < strlen($str));
        return $hex;
    }//fim str2Hex
    
    protected static function getCardName($tBand)
    {
        switch ($tBand) {
            case '01':
                $tBandNome = 'VISA';
                break;
            case '02':
                $tBandNome = 'MASTERCARD';
                break;
            case '03':
                $tBandNome = 'AMERICAM EXPRESS';
                break;
            case '04':
                $tBandNome = 'SOROCRED';
                break;
            case '99':
                $tBandNome = 'OUTROS';
                break;
            default:
                $tBandNome = '';
        }
        return $tBandNome;
    }
    
    /**
     * hex2Str
     * Converte hexadecimal ASCII para string
     *
     * @param  string $str
     * @return string
     */
    protected static function hex2Str($str)
    {
        if ($str == '') {
            return '';
        }
        $bin = "";
        $iCount = 0;
        do {
            $bin .= chr(hexdec($str[$iCount].$str[($iCount + 1)]));
            $iCount += 2;
        } while ($iCount < strlen($str));
        return $bin;
    }
    
    protected function makeQRCode(
        $chNFe,
        $url,
        $tpAmb,
        $cDest = '',
        $dhEmi = '',
        $vNF = '',
        $vICMS = '',
        $digVal = '',
        $idToken = '000001',
        $token = ''
    ) {
        $nVersao = '100';
        $dhHex = self::str2Hex($dhEmi);
        $digHex = self::str2Hex($digVal);
        $seq = '';
        $seq .= 'chNFe=' . $chNFe;
        $seq .= '&nVersao=' . $nVersao;
        $seq .= '&tpAmb=' . $tpAmb;
        if ($cDest != '') {
            $seq .= '&cDest=' . $cDest;
        }
        $seq .= '&dhEmi=' . strtolower($dhHex);
        $seq .= '&vNF=' . $vNF;
        $seq .= '&vICMS=' . $vICMS;
        $seq .= '&digVal=' . strtolower($digHex);
        $seq .= '&cIdToken=' . $idToken;
        //o hash code é calculado com o Token incluso
        $hash = sha1($seq.$token);
        $seq .= '&cHashQRCode='. strtoupper($hash);
        if (strpos($url, '?') === false) {
            $seq = $url.'?'.$seq;
        } else {
            $seq = $url.''.$seq;
        }
        return $seq;
    }
    

    protected function pNotaCancelada()
    {
        return $this->situacao !== null && strpos($this->situacao[0], 'CANCELADA') !== false;
    }

    protected function pNotaDenegada()
    {
        return $this->situacao !== null && strpos($this->situacao[0], 'DENEGADA') !== false;
    }
}
