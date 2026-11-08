# Changelog

All Notable changes to `sped-da` will be documented in this file.

Todas as atualizações a partir de 30/05/206 devem observar os principios [Mantendo o CHANGELOG](http://keepachangelog.com/).

## 0.1.26-cd.1 - 2026-11-08 (fork consultadanfe)

Base: tag v0.1.26 do upstream. Detalhes e motivos em [PATCHES.md](PATCHES.md).

### Added
- `NFe\DanfeRtc`: DANFE NF-e com IBS/CBS/IS no leiaute da NT 2026.010 (porte do PR nfephp-org/sped-da#686, de robsonrrr/rogeriobbvn-rolemak, com ajustes de dynne).
- `Common\XmlSanitizerTrait`: XML com texto livre mal-formado (HTML, `&`, `<` cru) passa a carregar.
- `CTe\DacteLeiauteTrait`: folhas de continuação no DACTE e no DACTE OS.
- `Danfce::setOffLineDoublePrint()`.
- Exemplos fictícios (CNPJ alfanumérico) em `examples/tipos/`, `examples/gerar.php` e PDFs em `examples/pdf/`.
- Smoke test (`tests/ExemplosTest.php`) e GitHub Actions em PHP 8.1, 8.2 e 8.3.

### Changed
- `Danfce`: DANFE NFC-e reescrito pelo Manual DANFE NFC-e v6.0 (altura dinâmica, contingência, cancelada/denegada, consumidor).
- `Damdfe`: leiaute em fluxo com quantas folhas forem necessárias (MOC MDF-e 3.00a); emitente lido de `<emit>`.
- `Dacte`/`DacteOSV3`: campos no limite do schema sem corte, tomador do CT-e 4.00, modais aéreo/dutoviário/multimodal.
- `Danfe`: alturas protegidas e `calculeHeight()`; texto longo legível; dados adicionais, duplicatas e descrições em folhas de continuação.
- `pTextBox`: sem microtexto (mín. 4,5 pt; quebra antes de reduzir) e Windows-1252 no lugar de `utf8_decode`.
- `composer.json`: PHP >= 8.1, `ext-dom`, `ext-mbstring`; PHPUnit 9.6 no require-dev.

### Fixed
- PHP 8.x: `$str{n}`, `number_format()` com string, `mktime()`/`date()` com string/vazio, tabelas do CODE-128 nulas, propriedades dinâmicas (8.2).
- Texto com `<` em infCpl/infAdProd não some mais (`pAnfavea`).
- CC-e: emitente vazio, autor CPF, evento sem `retEvento`.
- DV da chave adicional de contingência FS/FS-DA.

## 0.1.0-dev - 

### Added
- Nothing

### Deprecated
- Nothing

### Fixed
- Nothing

### Removed
- Nothing

### Security
- Nothing
