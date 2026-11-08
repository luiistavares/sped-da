# Patches deste fork sobre a sped-da 0.1.26

Base: tag `v0.1.26` do upstream (`d4db9f8`, 09/10/2019). Tudo o que muda em `src/` está
listado aqui, com o porquê. Os números são os mesmos do registro interno do
[consultadanfe.com](https://consultadanfe.com), onde estes patches rodam em produção; por isso
há lacunas (7 e 14) e um patch novo só do fork (19). Os comentários `PATCH n` /
`PATCH consultadanfe` no código apontam para estas seções. `SITE-10`/`SITE-11` citados em
comentários são tickets internos (descritos nos Patches 16 e 17).

Para ver o diff completo: `git diff v0.1.26 consultadanfe -- src`.

## Fora deste fork

- **Patches 7 e 14**: alteram o pacote `paseto/nfse-nacional-pdf` (DANFSe da NFS-e Nacional,
  leiaute v2.0 da NT SE/CGNFS-e 008), **não** a sped-da.
- Código do site que só usa a sped-da: detecção do tipo de documento, `normalizarEncoding()`
  (troca caracteres de controle proibidos no XML 1.0 por espaço antes de chamar a lib) e o
  fallback que remove os grupos da Reforma quando até o `DanfeRtc` falha.
- Os créditos do rodapé: no site eles são trocados pela marca do consultadanfe. Aqui ficam
  os do upstream ("Powered by NFePHP"); use `Danfe::creditsIntegratorFooter()` para o seu texto.
- A suíte de regressão do site (testes de leiaute por tipo). Aqui há um smoke test que
  renderiza os 30 exemplos (`tests/ExemplosTest.php`).

## Resumo por classe

| Documento | Classe | Patches |
|---|---|---|
| NF-e (55) | `NFe\Danfe` | 2, 3, 6, 9, 10, 11, 12, 18, 19 |
| NF-e com IBS/CBS/IS (NT 2026.010) | `NFe\DanfeRtc` (novo, estende `Danfe`) | DanfeRtc, 9, 18 |
| NFC-e (65) | `NFe\Danfce` | 1, 6, 11, 13, 19 |
| CT-e (57) | `CTe\Dacte` | 3, 4, 5, 6, 8, 12, 17 |
| CT-e OS (67) | `CTe\DacteOSV3` | 3, 6, 8, 12, 17 |
| MDF-e (58) | `MDFe\Damdfe` | 4, 6, 16 |
| CC-e / eventos de NF-e | `NFe\Daevento`, `NFe\Dacce` | 6, 15 |
| Eventos de CT-e | `CTe\Daevento`, `CTe\Dacce`, `CTe\Dacanc` | 6 |
| Base | `Legacy\Common`, `Legacy\Pdf` | 2, 4, 11, 12 |

---

## Patch 1 — Danfce.php: sintaxe `$str{n}` (PHP 7.4+)

`str2Hex()`/`hex2Str()` usavam `$str{$i}`, removido no PHP 8 (erro de sintaxe). Trocado por
`$str[$i]`.

## Patch 2 — Common.php `pConvertTime()` (PHP 8)

Devolvia `''` para data vazia e passava strings para `mktime()`; no PHP 8 `date()`/`mktime()`
exigem `int`. Agora devolve `null` para data vazia/inválida e converte os argumentos com
`(int)` (segundos ausentes = 0).

## Patch 3 — `number_format((float) ...)` (PHP 8)

`number_format()` recebia `nodeValue`/`getTagValue()` (sempre `string`); no PHP 8 isso é
`TypeError`. Cast `(float)` em todas as chamadas de `Danfe`, `Dacte`, `DacteV3` e `DacteOSV3`
(a `Danfce` e a `Damdfe` reescritas formatam o número como texto, sem `float`).

## Patch 4 — Pdf.php: `$setFrom`/`$setTo` inicializados (PHP 8)

As tabelas do CODE-128 eram `null` até o primeiro uso e o `strtr()` do `code128()` recebia
`null` (`TypeError`). Inicializadas com `['A' => '', 'B' => '', 'C' => '']`.

## Patch 5 — Dacte.php: modais aéreo (02) e dutoviário (06)

O `switch` de modais chamava `zModalAereo()`/`zModalDutoviario()`, que não existiam (fatal).
Primeiro viraram stubs; o Patch 17 trocou por quadros com os campos do Anexo II.

## Patch 6 — Sanitizador de XML mal-formado (`Common\XmlSanitizerTrait`, novo)

**Por quê:** emissores colocam HTML/JSON/`&`/`<` cru em campos de texto livre; o `DOMDocument`
não carrega o XML e a lib acusa "deve ser uma NF-e modelo 55" numa NF-e válida. Num lote real
de 165 NF-e, 18 carregavam antes e 154 depois.

**O quê:** antes do `loadXML()` de cada classe (Danfe, Danfce, Dacce, Daevento, Dacte,
DacteV3, DacteOSV3, Dacanc, Dacce/Daevento de CT-e, Damdfe):
1. escapa `&` que não forma entidade;
2. envolve em CDATA só as tags-folha de texto livre (infCpl, infAdProd, xObs, xLgr, xNome,
   xProd, natOp, xCorrecao...; nunca containers como `infAdic`), idempotente;
3. se o XML ainda não carregar, escapa todo `<` que não abre tag/comentário/CDATA válido.

XML bem formado passa intacto (CDATA preserva o conteúdo byte a byte).

## Patch 8 — Dacte/DacteOSV3 defensivos

- `getElementsByTagName(...) !== null` é sempre verdadeiro (devolve `DOMNodeList`): trocado
  por `->length > 0` no modal aquaviário (fatal com `infUnidCarga` ausente).
- `zImpostos()` com CT-e sem `<imp>`: retorna sem desenhar em vez de fatal.
- `DacteOSV3`: stubs dos modais aéreo/dutoviário (depois substituídos no Patch 17).
- `DacteV3` (classe antiga, mantida): mesmos fixes e `$qCarga` inicializado para cargas que
  não são em quilo.

## Patch 9 — Danfe.php: alturas protegidas e `calculeHeight()`

**Por quê:** o `DanfeRtc` precisa de cabeçalho, quadro de impostos e título da grade mais
altos e de faixas de item mais altas; o `montaDANFE()` tinha as alturas fixas e contava as
páginas com a altura do item clássico (itens sumiam, "Folha x/N" errado). Mesmo desenho do
PR #686.

**O quê:** `protected $hCabecalho = 47`, `$hImposto = 18`, `$hCabecalhoItens = 4` (padrões =
leiaute clássico); contagem de páginas começa no item 0 e usa `calculeHeight($item, $w2)`
(método novo, sobrescrito pelo `DanfeRtc`); item mais alto que uma página vazia não entra em
laço infinito. Com os padrões, o Clássico sai com o mesmo texto e as mesmas páginas.

## Patch 10 — Danfe.php: texto longo legível

Canhoto, endereço do emitente e ENDEREÇO/BAIRRO/MUNICÍPIO do destinatário eram reduzidos até
2–4 pt ou saíam da caixa. `fonteQueCabe()` e `valorQueCabe()` escolhem a maior fonte que cabe
(mín. 4,5 pt) e quebram a linha abaixo do rótulo quando preciso. Só age quando o texto não
cabia.

## Patch 11 — Texto livre literal e Windows-1252 (Danfe `pAnfavea`, Common)

- `pAnfavea()` tratava tudo do primeiro `<` ao último `>` de infCpl/infAdProd como XML
  Anfavea e descartava: "OBS: <p>ENTREGAR</p>" ou "PRECO < 10" sumiam. Agora só entra no modo
  Anfavea com as tags dela com atributos (`<id|div|entg|dest|ctl|ref ...=`).
- `pTextBox`/`pTextBox90` usavam `utf8_decode` (ISO-8859-1, deprecated no PHP 8.2): €, aspas
  curvas e travessão viravam "?". Agora `html_entity_decode` + `mb_convert_encoding(...,
  'Windows-1252')`, a codificação das fontes do FPDF (emoji sem glifo vira "?").

## Patch 12 — Common `pTextBox` sem microtexto; DACTE dentro da caixa

- `pTextBox` reduzia de 1 em 1 pt até caber (6 → 3 pt) e texto centralizado mais largo que a
  caixa começava fora da página. Agora: linha única que só caberia abaixo de 4,5 pt numa caixa
  com altura para 2+ linhas quebra; texto quebrado mais alto que a caixa reduz em passos de
  0,5 pt (mín. 4,5); trava contra laço sem fim. Não age com `$hmax`/`$vOffSet` nem `$h = 0`.
- `Dacte`/`DacteOSV3`: valores dos blocos e endereço do emitente presos à largura do quadro
  (passavam da borda/da página).

## Patch 13 — Danfce.php: DANFE NFC-e pelo Manual DANFE NFC-e v6.0

Miolo reescrito (construtor até `pAnfavea`); API pública igual (`monta()`, `render()`,
`printDANFE()`) e novo `setOffLineDoublePrint(bool)`.

**Por quê:** alturas fixas (9 mm por item), descrição longa vazando, `qCom`/`vUnCom` com 2
casas (0,001 → "0,00"), totais crus do XML, infAdFisco/xMsg/homologação/contingência/2ª via
ausentes, chave sem blocos, URL de consulta errada, data de **agora** quando sem protocolo,
cancelamento por evento não detectado, denegada como "CANCELADA", tPag 16–22 sem nome, NFC-e
sem `qrCode` sem PDF.

**O quê:**
- cada bloco mede o texto antes de desenhar (2 passos: mede → desenha); cada página tem a
  altura do conteúdo, até 5.000 mm (limite do PDF), com quebra entre itens e cabeçalho
  repetido; fontes de 6 a 12 pt;
- divisões na ordem do manual: emitente (CNPJ ou CPF), situação, itens, totais (desconto,
  frete, seguro, outras e "Valor a pagar" só se houver), pagamentos, troco, Lei 12.741,
  mensagens fiscais, consulta (`urlChave` do XML) e chave em 11 blocos, consumidor
  (CPF/CNPJ/estrangeiro ou "NÃO IDENTIFICADO"), número/série/emissão/protocolo, QR Code
  50 mm, infCpl;
- `numero()`: número do XML formatado como texto, todas as casas significativas, sem `float`;
- contingência (`tpEmis` 9): avisos e, sem protocolo, "Via Estabelecimento" (manual 3.1.8);
- cancelada (cStat 101/135/151/155 ou evento 110111/110112 no nfeProc) e denegada;
- sem `qrCode` no XML: sai sem a imagem (o QR não é montado da chave: exige CSC/assinatura);
- IBS/CBS/IS na NFC-e: ignorados no desenho (não há leiaute oficial no DANFE NFC-e).

## Patch 15 — NFe/Daevento.php (CC-e)

- Quadro do emitente vazio (a razão social vinha de `aEnd`, raramente passado): imprime o
  CNPJ/CPF do autor formatado (alfanumérico ok).
- Autor com CPF lia `CNPJ->item(0)` sem checar (Warning).
- Sem destinatário no `retEvento`, o parágrafo de abertura era reimpresso em 12 pt sobre o
  quadro da nota.
- XML só com o `<evento>` assinado (sem `retEvento`) derrubava a geração; agora sai com
  "Protocolo: SEM REGISTRO NA SEFAZ" e sem data de registro inventada.
- "Prococolo" → "Protocolo"; `$tpEvento` indefinido.

## Patch 16 — Damdfe.php: emitente certo e leiaute em fluxo

Header/body/footer reescritos; construtor, `buildMDFe()`, `printMDFe()` e `render()` iguais.

**Por quê:** o emitente era o 1º `<CPF>` do documento (o do condutor) e o CNPJ sumia; a IE
perdia dígitos; o DAMDFE tinha uma folha só e omitia municípios, seguro e produto; 10
condutores ou 3 reboques sobrepunham caixas; infCpl longo saía da página; "Impresso em
contingência - <agora>" inventava data.

**O quê** (MOC MDF-e 3.00a, Anexo II: "tantas folhas quantas forem necessárias"):
- primitivas próprias (nada < 6 pt, célula cresce, linhas continuam na folha seguinte com
  cabeçalho repetido e "(continuação)"); "Folha x/N" em todas;
- quadros: emitente + QR Code 30 mm, identificação, quantidades/peso/valor, protocolo,
  CODE-128, rodoviário (veículos, condutores, vale-pedágio, CIOT, contratantes), aéreo,
  aquaviário, ferroviário, observações, carregamento/percurso, produto predominante, seguro,
  lacres e documentos por município de descarga;
- homologação, contingência, cancelado e encerrado (cStat ou evento anexado) com marca
  d'água; datas do XML como vieram; "Impresso em" no fuso de São Paulo;
- propriedades antes dinâmicas declaradas (PHP 8.2).

## Patch 17 — DACTE e DACTE OS: campo no limite do schema sem corte (folhas de continuação)

Arquivo novo `CTe\DacteLeiauteTrait` + trechos `PATCH 17` em `Dacte.php`/`DacteOSV3.php`.
Regras: Manual do DACTE v4.00 (MOC 4.00, Anexo II) §2.3, §2.4, §2.12, §2.21.2, §3/§4.

**Trait:** `zCaixaTexto()` (maior fonte até 4,5 pt; o que sobra volta), `zCampo()` (valor que
não cabe sai com "(...)" e inteiro nas OBSERVAÇÕES), `zTextoComContinuacao()`,
`zContinuacao()` (folhas adicionais com cabeçalho, FL n/total e as mesmas colunas),
`montaDACTE` em duas passadas para saber o total de folhas (4–34 ms).

**Dacte (57):** tomador `toma3`/`toma4` do leiaute 3.00/4.00 (o quadro saía **vazio em todo
CT-e 4.00**); `forPag` inexistente imprimia "ERRO"; chave em blocos de 4; documentos
originários em lista única com continuação (a 16ª NF-e sumia); participantes com xCpl/xBairro;
medidas presas às colunas; componentes na continuação; ICMS do Simples, ICMS60/OutraUF;
complementar (`chCTe`), anulação e substituto; CT-e sem `<dest>` (fatal); modais aéreo,
dutoviário e multimodal com quadro próprio; fone de 11 dígitos.

**DacteOSV3 (67):** código de barras 85 mm centralizado (era 75 mm, módulo 0,225 mm), QR Code
26 mm e campo FL; tomador/dados adicionais do Dacte; documentos referenciados (`infDocRef`,
antes descartados); `infAdFisco` (antes descartado); seguradora e apólice (sempre em branco);
modais 02–05 sem cobrir o quadro do seguro.

**Reforma:** XML de CT-e com `IBSCBS` renderiza normalmente; não existe leiaute oficial de
DACTE com IBS/CBS, então nada foi acrescentado.

## Patch 18 — Danfe.php: nada se perde nos limites do schema

Vale para o Clássico e o `DanfeRtc`. Cada mudança só age quando o dado não cabia.

1. Dados adicionais maiores que a folha 1: o quadro é limitado e o restante sai em folhas
   "DADOS ADICIONAIS - CONTINUAÇÃO" (contadas no "Folha x/N"); antes o quadro era desenhado
   por cima do cabeçalho.
2. Duplicatas além das que cabem no quadro vão, na ordem, para os dados adicionais (antes a
   22ª–28ª invadia o cálculo do imposto e da 29ª em diante nada saía).
3. `rastro` (lote/fabricação/validade) nunca saía (`isset()` num `DOMNodeList`).
4. `veicProd` desenhado sem altura reservada (o item seguinte caía por cima): vai na descrição.
5. Descrição de item mais alta que uma folha: o excedente vai para os dados adicionais.
6. Números de 13 dígitos na grade: reduz até 4,5 pt ou quebra só após "." ou ",".
7. Transporte: valores de 60 caracteres não atravessam mais a borda.
8. Chave adicional FS/FS-DA: `vNF * 100` em float truncava (19,99 → 1998) e o DV saía errado;
   agora `round()`.
9. `nfeProc` sem `protNFe`: sem Warnings.

Fora do escopo (decisão do upstream, mantida): quadro de PAGAMENTO, mais de 2 NFref, comb/arma
no Clássico.

## Patch 19 — Propriedades dinâmicas (só no fork)

PHP 8.2 deprecou a criação dinâmica de propriedades. Declaradas como `public` (como as
dinâmicas eram): `Danfe::$nfeProc`, `Danfe::$situacao_externa` e, na `Danfce`, `$dom`,
`$orientacao`, `$logoAlign`, `$numero_registro_dpec`, `$wPrint`, `$hPrint`. Achadas pelo smoke
test, que falha com qualquer aviso do PHP.

## DanfeRtc — DANFE NF-e com IBS/CBS/IS (NT 2026.010)

Arquivo novo `src/NFe/DanfeRtc.php` (estende `Danfe`; depende do Patch 9).

- Ponto de partida: `DanfeRtc` do PR [nfephp-org/sped-da#686](https://github.com/nfephp-org/sped-da/pull/686)
  (aberto por **robsonrrr**, commits de **rogeriobbvn-rolemak**, ajustes de **dynne**), portado para a 0.1.26 e alinhado ao texto da
  NT 2026.010 v1.00 (rótulos, pareamento de alíquotas e valores, campos opcionais), com os
  campos da NT 2025.002 v1.53.
- Mantém o Modelo Clássico (canhoto, cabeçalho, destinatário, fatura, transporte, ISSQN,
  dados adicionais, rodapé, marcas de homologação/cancelada/denegada) e redesenha só o que a
  NT muda: linha de CRT/regime de apuração no cabeçalho, totais em três blocos (produtos e
  nota; ICMS/IPI; IBS/CBS/IS) e grade de itens com as linhas de tributo por item.
- Regra da NT (§4.4): valor ausente no XML fica em branco; nada é inferido nem calculado. O
  regime de apuração fica em branco até a NT ligar a tag.
- Quando usar: XML com algum dos grupos `IBSCBS`, `IS`, `IBSCBSTot` ou `ISTot` (ver
  `examples/gerar.php`). A decisão é pelos dados, não pela data de emissão.

## Fora de `src/`

- `composer.json`: `php >= 8.1`, `ext-dom` e `ext-mbstring` declarados; `require-dev` trocado
  por PHPUnit 9.6 (as versões antigas de phpunit/phpstan/phpcpd não instalam no PHP 8);
  script `exemplos`. **Nome do pacote mantido** (`nfephp-org/sped-da`).
- `phpunit.xml.dist`, `tests/ExemplosTest.php`, `.github/workflows/tests.yml`, `examples/`.

## Patch 20 — Danfe.php: NF-e reais sem grupos e grade sem cruzar coluna

Vindo do corpus de XMLs reais do consultadanfe.com (2026-11-08):
- `dest`, `enderDest`, `enderEmit`, `total`, `transp` ou `imposto` ausentes
  viram campo em branco em vez de "getElementsByTagName() on null" (e sem o
  "0-Por conta do Rem" inventado quando não há `transp`).
- `pesoL`/`pesoB`/`qVol` com vírgula decimal ou texto não numérico ("NaN")
  saem como vieram, sem quebrar nem virar 12,000.
- Grade clássica: descrição quebra 0,5 mm dentro da coluna; valores vizinhos
  colados e códigos longos reduzem em passos de 0,5 pt (mín. 4,5) ou quebram.
- Item com descrição longa no fim da página não empurra os seguintes para o rodapé.
