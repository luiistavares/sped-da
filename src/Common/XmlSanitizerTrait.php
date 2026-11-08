<?php

namespace NFePHP\DA\Common;

/**
 * PATCH consultadanfe — Trait de sanitizacao de XMLs mal-formados.
 *
 * Problema: emissores colocam HTML/JSON/JavaScript cru em campos de
 * texto livre (infCpl, xObs, etc), quebrando o parser DOMDocument.
 * Resultado: elementos como <ide> nao sao indexados, validador interno
 * falha com "deve ser uma NF-e modelo X".
 *
 * Solucao em 2 camadas:
 *  Camada 1 (preventiva): wrap em CDATA todos os 28+ campos de texto livre
 *  Camada 2 (fallback):    se loadXML ainda falhar, escapa '<' invalidos
 *
 * Uso:
 *   class MinhaClasse {
 *     use \NFePHP\DA\Common\XmlSanitizerTrait;
 *
 *     // Antes de loadXML($this->xml):
 *     $this->xml = self::sanitizeXmlFreeText($this->xml);
 *     // Tente loadXML. Se falhar:
 *     $this->xml = self::aggressiveEscape($this->xml);
 *   }
 *
 * Cobre: NFe, NFCe, CTe, CTe-OS, MDFe, CC-e, eventos.
 */
trait XmlSanitizerTrait
{
    /**
     * Camada 1: wrap em CDATA todos os campos de texto livre.
     * Idempotente — XMLs corretos passam intactos.
     *
     * @param  string $xml
     * @return string
     */
    public static function sanitizeXmlFreeText($xml)
    {
        // Camada 1a: corrige '&' solto (que nao seja entidade conhecida) — escapa pra &amp;
        // Antes do CDATA wrap pra que entidades quebradas em campos NAO listados tambem sejam corrigidas.
        $xml = preg_replace(
            '/&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/u',
            '&amp;',
            $xml
        );

        // Camada 1b: wrap em CDATA campos de texto livre (folhas/leaf-only — NUNCA containers).
        // IMPORTANTE: 'infAdic' NAO esta nessa lista — ela e container de outras tags.
        // Wrappear container em CDATA esconderia subtags do DOMDocument e quebraria a indexacao.
        $freeTextTags = [
            // NFe/NFCe — texto livre (folhas)
            'infCpl', 'infAdProd', 'infAdFisco',
            // Enderecos (folhas)
            'xLgr', 'xCpl', 'xBairro', 'xMun', 'xPais',
            // Nomes (folhas)
            'xNome', 'xFant', 'xResp', 'xContato',
            // Observacoes (folhas)
            'xObs', 'xObsCont', 'xObsFisco', 'xTexto',
            // Justificativas / Motivos / Correcoes
            'xJust', 'xMotivo', 'xCondUso', 'xCorrecao', 'xCorrecaoDet',
            // Mensagens / Custom
            'xMsg', 'xCampo',
            // Locais (NFe export)
            'xLocPag', 'xLocExporta', 'xLocDespacho',
            // Produtos / Items
            'xProd', 'xPed', 'xLanc',
            // Operacao
            'natOp',
            // CTe (folhas)
            'xOutCat', 'xObsServ', 'xConteudo',
            // MDFe (folhas)
            'xCarrega', 'xDescarga', 'xPercurso',
            // Eventos (folhas)
            'xCondicaoUso', 'descEvento', 'xEvento',
        ];

        foreach ($freeTextTags as $tag) {
            $xml = preg_replace_callback(
                '/<' . $tag . '>([\s\S]*?)<\/' . $tag . '>/u',
                function ($m) use ($tag) {
                    $content = $m[1];
                    // Ja esta em CDATA - nao mexe
                    if (strpos($content, '<![CDATA[') === 0) {
                        return $m[0];
                    }
                    // Sem markup literal - nao precisa sanitizar
                    if (strpos($content, '<') === false && strpos($content, '>') === false) {
                        return $m[0];
                    }
                    // Escapa ']]>' que quebrariam o CDATA wrapper
                    $content = str_replace(']]>', ']]]]><![CDATA[>', $content);
                    return '<' . $tag . '><![CDATA[' . $content . ']]></' . $tag . '>';
                },
                $xml
            );
        }
        return $xml;
    }

    /**
     * Camada 2: fallback agressivo. Escapa todo '<' que NAO forma tag XML
     * valida. Acionar APENAS se Camada 1 nao foi suficiente.
     *
     * Tag valida: <?xml, <!DOCTYPE, <!--, <![CDATA[, <abc, </abc
     *
     * @param  string $xml
     * @return string
     */
    public static function aggressiveEscape($xml)
    {
        $validTagStart = '/<(?=(?:\?xml|!DOCTYPE|!--|!\[CDATA\[|\/?[a-zA-Z]))/u';

        preg_match_all($validTagStart, $xml, $matches, PREG_OFFSET_CAPTURE);
        $validPositions = [];
        foreach ($matches[0] as $m) {
            $validPositions[$m[1]] = true;
        }

        $result = '';
        $len = strlen($xml);
        for ($i = 0; $i < $len; $i++) {
            $ch = $xml[$i];
            if ($ch === '<' && !isset($validPositions[$i])) {
                $result .= '&lt;';
            } else {
                $result .= $ch;
            }
        }
        return $result;
    }

    /**
     * Helper que aplica as 2 camadas em sequencia. Use no construtor das
     * classes Danfe, Danfce, Dacte, etc. antes do loadXML.
     *
     * @param  string $xml XML original (pode estar mal-formado)
     * @return string XML sanitizado pronto pra parsear
     */
    public static function sanitizeXmlFully($xml)
    {
        // Camada 1
        $sanitized = self::sanitizeXmlFreeText($xml);

        // Verifica se Camada 1 foi suficiente
        $useInternal = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($sanitized);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($useInternal);

        // Se ainda tem erro de parse, aplica Camada 2 (escape agressivo)
        if (!$loaded || !empty($errors)) {
            $sanitized = self::aggressiveEscape($sanitized);
        }

        return $sanitized;
    }
}
