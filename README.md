# sped-da (fork consultadanfe)

> **English summary.** Fork of [nfephp-org/sped-da](https://github.com/nfephp-org/sped-da)
> **v0.1.26** (the last upstream tag, 2019) with the fixes used in production by
> [consultadanfe.com](https://consultadanfe.com): PHP 8.1–8.3 compatibility, the
> Brazilian tax-reform DANFE (IBS/CBS/IS, NT 2026.010) via the new `DanfeRtc` class,
> alphanumeric CNPJ (NT 2026.004), no micro-text, literal special characters, an NFC-e
> layout following the DANFE NFC-e manual v6.0, a flowing multi-page MDF-e layout and
> DACTE continuation sheets. Same package name and namespace, so it is a drop-in
> replacement through a Composer `vcs` repository. Docs are in Portuguese; see
> [PATCHES.md](PATCHES.md) for every change. License unchanged (LGPL-3.0+ / GPL-3.0+ / MIT).

Branch **`consultadanfe`** = tag `v0.1.26` do upstream + os patches descritos em
[PATCHES.md](PATCHES.md). O nome do pacote (`nfephp-org/sped-da`), o namespace
(`NFePHP\DA\`) e a API pública são os mesmos do upstream: quem já usa a sped-da 0.1.26
troca só a origem do pacote.

## O que muda em relação à 0.1.26 do upstream

| Área | Mudança | Patch |
|---|---|---|
| **Reforma Tributária** | `NFePHP\DA\NFe\DanfeRtc`: DANFE NF-e com IBS/CBS/IS no leiaute da **NT 2026.010** (produção em 01/12/2026), sobre o Modelo Clássico. Porte do PR [nfephp-org/sped-da#686](https://github.com/nfephp-org/sped-da/pull/686). Tag ausente fica em branco: nada é calculado nem inventado. | DanfeRtc, 9 |
| **CNPJ alfanumérico** | Chave e CNPJ com letras (NT 2026.004, a partir de 01/07/2026): formatação, CODE-128 e DV já funcionam; todos os exemplos usam CNPJ alfanumérico. | — |
| **PHP 8.1 a 8.3** | `$str{n}` removido, `number_format()` com `(float)`, `date()`/`mktime()` com `int`, propriedades inicializadas/declaradas (sem *dynamic property* do 8.2), `utf8_decode` trocado. | 1, 2, 3, 4, 11, 19 |
| **XML mal-formado** | Texto livre com HTML/`&`/`<` cru (infCpl, xObs...) não derruba mais o parser: CDATA preventivo + escape de fallback (`XmlSanitizerTrait`). | 6 |
| **Legibilidade** | Nada vira microtexto: `pTextBox` quebra linha antes de reduzir abaixo de 4,5 pt; canhoto, endereços e transporte cabem nas caixas. | 10, 12 |
| **Caracteres especiais** | infCpl/infAdProd com `<`, HTML ou "PRECO < 10" sai literal (antes sumia); €, aspas curvas e travessão saem certos (Windows-1252). | 11 |
| **NF-e no limite do schema** | Dados adicionais, duplicatas e descrição de item que não cabem continuam em folhas extras (contadas no "Folha x/N"); `rastro` impresso; números longos sem atravessar a borda; DV da chave de contingência FS/FS-DA corrigido. | 18 |
| **NFC-e** | DANFE NFC-e reescrito pelo **Manual DANFE NFC-e v6.0**: altura dinâmica (1 a 990 itens), contingência e 2ª via, cancelada por evento, denegada, consumidor CPF/CNPJ/estrangeiro, NFC-e sem QR Code, números sem arredondar. | 13 |
| **MDF-e** | DAMDFE em fluxo, com quantas folhas forem necessárias (MOC 3.00a): todos os modais, condutores, reboques, vale-pedágio, seguro, produto predominante, lacres e documentos por município; emitente lido de `<emit>`; homologação, contingência, cancelado e encerrado. | 16 |
| **CT-e / CT-e OS** | DACTE e DACTE OS com folhas de continuação (Manual do DACTE v4.00): campo que não cabe vai inteiro para as observações; tomador do CT-e 4.00 (estava vazio), modais aéreo/dutoviário/multimodal, CT-e sem `<dest>`/`<imp>`, documentos além do 15º. | 5, 8, 12, 17 |
| **CC-e (evento de NF-e)** | Emitente impresso pelo CNPJ/CPF do autor, autor CPF, evento sem `retEvento`, texto não reimpresso sobre o quadro. | 15 |

Ficaram **fora** deste fork os Patches **7 e 14** do consultadanfe: eles alteram outro pacote
(`paseto/nfse-nacional-pdf`, DANFSe da NFS-e Nacional), não a sped-da. Também não vêm as
partes que vivem no código do site (detecção de tipo, `normalizarEncoding()`, fallback que
remove os grupos da Reforma). Detalhes em [PATCHES.md](PATCHES.md).

## Instalação (Composer, repositório VCS)

O pacote mantém o nome `nfephp-org/sped-da`. Com um repositório `vcs` apontando para este
fork, o Composer passa a buscar o pacote aqui no lugar do Packagist:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/luiistavares/sped-da" }
    ],
    "require": {
        "nfephp-org/sped-da": "0.1.26-p1"
    }
}
```

Sobre as tags:

- **`v0.1.26-cd.1`**: nome da versão do fork (release no GitHub). O Composer **ignora** essa
  tag, porque `-cd.1` não é um sufixo de versão válido para ele.
- **`v0.1.26-p1`**: a mesma revisão, com nome que o Composer entende (`0.1.26.0-patch1`,
  estável, acima da 0.1.26 e abaixo de uma futura 0.1.27). É a que se usa no `require`.
- Alternativa: fixar o commit, `"nfephp-org/sped-da": "dev-consultadanfe#<sha>"`.

Como o fork também carrega as tags antigas do upstream, `"0.1.26"` instalaria a versão
original sem patches: use exatamente `0.1.26-p1` (ou o commit).

Requisitos: PHP **8.1+**, `ext-dom`, `ext-mbstring` (e o `ext-gd`/`ext-bcmath` exigidos pelo
`tecnickcom/tc-lib-barcode`).

## Uso

```php
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\NFe\DanfeRtc;

$xml = file_get_contents('nfe.xml');
// NF-e com grupos IBSCBS/IS/IBSCBSTot/ISTot: leiaute da NT 2026.010; senão, o Clássico.
$temReforma = (bool) preg_match('/<(IBSCBSTot|ISTot|IBSCBS|IS)\b[^>]*>/', $xml);
$danfe = $temReforma ? new DanfeRtc($xml) : new Danfe($xml);
$danfe->montaDANFE();
file_put_contents('danfe.pdf', $danfe->render());
```

As demais classes seguem a API do upstream: `Danfce::monta()`, `Dacte::montaDACTE()`,
`DacteOSV3::monta()`, `Damdfe::buildMDFe()`, `Daevento::montaDaEventoNFe()` +
`printDocument('', 'S')`, todas com `render()` devolvendo o PDF em string. Na `Damdfe`, passe
orientação e papel explícitos e `mododebug` 0: `new Damdfe($xml, 'P', 'A4', '', 'I', '', '', 0)`.

## Exemplos e testes

- [`examples/tipos/`](examples/tipos): 30 XMLs **fictícios** (CNPJ alfanumérico
  `12ABC345000188`, CPFs/CNPJs de teste, assinatura `FICTICIA`) de NF-e, NF-e com Reforma,
  NFC-e, CT-e, CT-e OS, MDF-e e CC-e, vários no tamanho máximo do schema.
- [`examples/gerar.php`](examples/gerar.php): escolhe a classe pelo conteúdo e grava os PDFs
  em [`examples/pdf/`](examples/pdf) (já versionados, para ver o resultado sem instalar nada).
- `tests/ExemplosTest.php`: renderiza todos os exemplos e exige `%PDF-` sem nenhum aviso do
  PHP antes; roda no GitHub Actions em PHP 8.1, 8.2 e 8.3.

```bash
composer install
php examples/gerar.php     # ou: composer exemplos
vendor/bin/phpunit         # ou: composer test
```

## Créditos e licença

- [NFePHP](https://github.com/nfephp-org/sped-da) e seus contribuidores: a sped-da original.
  Os créditos "Powered by NFePHP" dos rodapés foram mantidos.
- `DanfeRtc`: ponto de partida no PR [nfephp-org/sped-da#686](https://github.com/nfephp-org/sped-da/pull/686),
  de **robsonrrr** (commits de **rogeriobbvn-rolemak**), com ajustes de **dynne**; portado para a 0.1.26 e alinhado ao texto da
  NT 2026.010.
- Patches: equipe do [consultadanfe.com](https://consultadanfe.com).

Licença do upstream, sem mudança: **LGPL-3.0-or-later, GPL-3.0-or-later ou MIT**, à escolha
(ver [LICENSE](LICENSE) e `composer.json`). Este fork não abre PR no upstream por enquanto;
os patches estão aqui para quem quiser aproveitar ou propor lá.

---

# README original do upstream

# sped-da

Classes para geração dos documentos auxiliares usados pelos padrões Sped

> NOTA: Este repositório contêm as classes "LEGADAS", para criação dos PDF's do projeto original NFePHP.
> Porém essas classes foram ajustadas e alguns recursos estarão ausentes ou pelo menos diferentes das suas contrapartes originais.

> Serão retiradas das classes todas os recursos considerados como não "pertencentes" ao escopo das mesmas e não serão mais aceitas inclusões de métodos referentes a particuliaridades de qualuqer sistema.
> Estas classes devem ser e permanecer o tão genéricas quanto possivel.
 
# UNDER DEVELOPMENT (NOT FULLY READY)

[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![License][ico-license]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]

[![Issues][ico-issues]][link-issues]
[![Forks][ico-forks]][link-forks]
[![Stars][ico-stars]][link-stars]
[![Chat][ico-gitter]][link-gitter]


# Orientação
Abaixo seguem as orientações gerais para desenvolvedores que desejarem contribuir para a construção e melhoria dos códigos.

> Uma novo projeto oriundo do TCPDF está em desenvolvimento por Nicola Azuni. Este novo projeto é uma evolução do TCPDF e está sendo estruturado de acordo com as práticas mais atuais do PHP.

> Dito isso, estas classes do sped-da, que são legadas do projeto NFePHP, deverão ser refatoradas, assim que esse novo projeto [tc-lib-pdf](https://github.com/tecnickcom/tc-lib-pdf) estiver em uma versão usável.

## Estrutura das classes

Para a geração dos documentos auxiliares devem feitas as seguintes considerações:

* Esta biblioteca deverá ser escrita para PHP7, não será testado ou aceito o seu uso em ambientes com php menor que o 5.6 (por ora, pelo menos durante o seu desenvolvimento) e apartir da sua primeira versão estável somente PHP >= 7.0 será aceitável.
* Este pacote *"sped-da"* se tornará uma dependência (sugerida) dos demais, e será de competência do desenvolvedor coloca-la como dependência de sua aplicação ou não, usando o composer.
* A renderização das classes principais (Danfe, Dacte, Damdfe, Dacce e o NFCe, este com ressalvas) devem ser feita em PDF ou em HTML.
* Essas classes principais devem extender a classe Da.php que é a construtora básica.
* Os documentos auxiliares podem ser renderizados a partir dos XMLs ou das classes construtoras, estabelecidas em cada pacote. Ou seja, tanto pode ser passada uma classe com os dados do documento como o próprio documento em XML.
* Deve ser permitida e facilitada a criação de um PDF com múltiplos documentos.
* Todas as classes devem observar os principios S.O.L.I.D. e atender aos PSR-2 e PSR-4.
* Todos os métodos devem possuir testes unitários utilizando o phpunit, de forma a evitar a quebra do funcionamento das classes.
* Será montado um esquema que permitirá o "pull request" apenas se os testes unitários não falharem.
* No caso especifico da NFC-e (Nota fiscal do consumidor) existe um outro pacote que poderá vir a ser usado, trata-se do [*"posprint"*](https://github.com/nfephp-org/posprint) devido ao fato desse documento auxiliar normalmente ser impresso em impressoras térmicas POS, que não trabalham adequadamente com PDF e devem receber os dados em sua pópria linguagem (RAW data). Dessa forma o pacote *"posprint"* poderá tornar-se ser uma das dependências desse pacote.
* Para a conversão dos dados em PDF é necessário o uso de uma biblioteca que seja ativamente mantida, usada por um grande contingênte de programadores e que atenda minimamente os PSR. Uma grande atenção deve ser dedicada a esse ponto pois com a inclusão do PHP7 podem surgir problemas de incompatibilidade com bibliotecas que não sejam mantidas atualizadas.

## Contribuindo
Este é um projeto totalmente *OpenSource*, para usa-lo e modifica-lo você não paga absolutamente nada. Porém para continuarmos a mante-lo é necessário qua alguma contribuição seja feita, seja auxiliando na codificação, na documentação ou na realização de testes e identificação de falhas e BUGs.

**Este pacote esta listado no [Packgist](https://packagist.org/) foi desenvolvido para uso do [Composer](https://getcomposer.org/), portanto não será explicitada nenhuma alternativa de instalação.**

*Durante a fase de desenvolvimento e testes este pacote deve ser instalado com:*
```bash
composer require nfephp-org/sped-da:dev-master
```

*Ou ainda alterando o composer.json do seu aplicativo inserindo:*
```json
"require": {
    "nfephp-org/sped-da" : "dev-master"
}
```

> NOTA: Ao utilizar este pacote ainda na fase de desenvolvimento não se esqueça de alterar o composer.json da sua aplicação para aceitar pacotes em desenvolvimento, alterando a propriedade "minimum-stability" de "stable" para "dev".
> ```json
> "minimum-stability": "dev"
> ```

*Os stable realeases estão disponíveis (mas com algumas classes ainda em desenvolvimento), pode ser instalado com:*
```bash
composer require nfephp-org/sped-da
```
Ou ainda alterando o composer.json do seu aplicativo inserindo:
```json
"require": {
    "nfephp-org/sped-da" : "^0.1"
}
```

## Forma de uso
[DANFE](DANFE.md) 

## Log de mudanças e versões
Acompanhe o [CHANGELOG](CHANGELOG.md) para maiores informações sobre as alterações recentes.

## Testing

Todos os testes são desenvolvidos para operar com o PHPUNIT

## Security

Caso você encontre algum problema relativo a segurança, por favor envie um email diretamente aos mantenedores do pacote ao invés de abrir um ISSUE.

## Credits

Roberto L. Machado (owner and developer)

## License

Este pacote está diponibilizado sob LGPLv3 ou MIT License (MIT). Leia  [Arquivo de Licença](LICENSE.md) para maiores informações.


[ico-stars]: https://img.shields.io/github/stars/nfephp-org/sped-nfe.svg?style=flat-square
[ico-forks]: https://img.shields.io/github/forks/nfephp-org/sped-da.svg?style=flat-square
[ico-issues]: https://img.shields.io/github/issues/nfephp-org/sped-da.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/nfephp-org/sped-da/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/nfephp-org/sped-da.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/nfephp-org/sped-da.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/nfephp-org/sped-da.svg?style=flat-square
[ico-version]: https://img.shields.io/packagist/v/nfephp-org/sped-da.svg?style=flat-square
[ico-license]: https://poser.pugx.org/nfephp-org/nfephp/license.svg?style=flat-square
[ico-gitter]: https://img.shields.io/badge/GITTER-4%20users%20online-green.svg?style=flat-square


[link-packagist]: https://packagist.org/packages/nfephp-org/sped-da
[link-travis]: https://travis-ci.org/nfephp-org/sped-da
[link-scrutinizer]: https://scrutinizer-ci.com/g/nfephp-org/sped-da/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/nfephp-org/sped-da
[link-downloads]: https://packagist.org/packages/nfephp-org/sped-da
[link-author]: https://github.com/nfephp-org
[link-issues]: https://github.com/nfephp-org/sped-da/issues
[link-forks]: https://github.com/nfephp-org/sped-da/network
[link-stars]: https://github.com/nfephp-org/sped-da/stargazers
[link-gitter]: https://gitter.im/nfephp-org/sped-da?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge

