<div align="center">

<img src="https://raw.githubusercontent.com/twitter/twemoji/master/assets/svg/1f4cf.svg" width="100" alt="Code Limits">

# `gboquizosanchez/phpstan-code-limits`

**Size, layout and expression limits for PHP code, as PHPStan rules**

[![Latest Stable Version](https://img.shields.io/packagist/v/gboquizosanchez/phpstan-code-limits.svg)](https://packagist.org/packages/gboquizosanchez/phpstan-code-limits)
[![Total Downloads](https://img.shields.io/packagist/dt/gboquizosanchez/phpstan-code-limits.svg)](https://packagist.org/packages/gboquizosanchez/phpstan-code-limits)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4?logo=php&logoColor=white)](https://packagist.org/packages/gboquizosanchez/phpstan-code-limits)
[![License: MIT](https://img.shields.io/badge/License-MIT-22C55E.svg)](LICENSE.md)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%20max-blue)](https://phpstan.org/)
[![Tests](https://img.shields.io/badge/Tests-Pest%20v4-9C27B0)](https://pestphp.com/)

---

*Too many parameters, a 120-character signature, a non-static closure?*
*`composer test` fails until it is fixed.*

</div>

---

## Why code limits?

Style rules written in a guide get skipped, and reviewing them by hand is slow. This extension turns
each limit into a regular PHPStan error with its own identifier, so the gate fails and whoever wrote
the code fixes it before review.

| Problem                                   | Without code limits         | With code limits                          |
|-------------------------------------------|-----------------------------|-------------------------------------------|
| A method grows to 6 parameters            | ❌ Spotted in review, maybe | ✅ `codeLimits.params` fails the analysis |
| A long signature on a single line         | ❌ Reformatted by hand      | ✅ `codeLimits.signature` points at it    |
| A closure that could be `static`          | ❌ Nobody notices           | ✅ `codeLimits.closureStatic`             |
| A function nobody can follow anymore      | ❌ Grows until it breaks    | ✅ Cognitive complexity capped at 15      |
| One rule does not fit a folder            | ❌ Ad hoc exceptions        | ✅ PHPStan's own `ignoreErrors`           |

---

## Version compatibility

| Package | PHPStan | PHP  |
|---------|---------|------|
| 1.x     | 2.x     | ^8.4 |

---

## 🚀 Features

- **One identifier per limit**: every error is `codeLimits.<rule>`, so each limit can be ignored on its own.
- **Configurable numbers**: parameters, returns, methods and line length are PHPStan parameters.
- **Cognitive complexity included**: ships [`tomasvotruba/cognitive-complexity`](https://github.com/TomasVotruba/cognitive-complexity) with a function limit of 15.
- **One-command setup**: `code-limits-install` wires everything into your `phpstan.neon`, with or without `phpstan/extension-installer`.
- **Dev only**: `--dev` dependency, nothing runs in production.

---

## 📦 Installation

```bash
composer require --dev gboquizosanchez/phpstan-code-limits
```

Then add the configuration to your `phpstan.neon`:

```bash
vendor/bin/code-limits-install
```

It adds `complexity.neon` to the `includes`, which sets the cognitive complexity limit. That file has
to be included by the project because `tomasvotruba/cognitive-complexity` loads its own defaults
(function 9, class 40) after this extension. Without
[`phpstan/extension-installer`](https://github.com/phpstan/extension-installer) it also adds both
extensions. Running it again changes nothing.

---

## 📋 Limits

| Identifier                   | Limit                                                                                                                                         |
|------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------|
| `codeLimits.params`          | At most 3 parameters per function or method. Promoted constructor parameters of a readonly class do not count.                                |
| `codeLimits.returns`         | At most 3 return statements per function or method.                                                                                           |
| `codeLimits.methods`         | At most 20 methods per class-like.                                                                                                            |
| `codeLimits.signature`       | A signature longer than 80 characters has one parameter per line with a trailing comma; a shorter one stays on one line.                      |
| `codeLimits.promotedCtor`    | A constructor with promoted properties has one parameter per line with a trailing comma.                                                      |
| `codeLimits.throw`           | `throw_if()` / `throw_unless()` on one line when it fits in 80 characters, otherwise one argument per line; condition and message stay inline. |
| `codeLimits.matchArm`        | A `match` arm with several conditions has one condition per line with a trailing comma.                                                       |
| `codeLimits.tuple`           | No returning an array just to destructure it with `[$a, $b] = ...`.                                                                           |
| `codeLimits.foreach`         | `foreach` iterates over a variable, a property or a single call, and its header fits in 80 characters.                                        |
| `codeLimits.nestedArgs`      | No two or more nested call levels in arguments, unless the statement fits in 80 characters.                                                   |
| `codeLimits.closureStatic`   | Closures and arrow functions are `static` unless they use `$this` or are bound by the framework.                                              |
| `codeLimits.closureUseOrder` | `use (...)` variables are ordered by first usage in the body.                                                                                 |
| `codeLimits.concat`          | String interpolation or `sprintf()` instead of `.` concatenation.                                                                             |
| `codeLimits.ellipsis`        | Three ASCII dots `...` instead of the single ellipsis character (U+2026).                                                                     |
| `complexity.functionLike`    | Cognitive complexity of a function or method at most 15.                                                                                      |

---

## ⚙️ Configuration

The numbers are configurable:

```neon
parameters:
    codeLimits:
        maxParameters: 3
        maxReturns: 3
        maxMethods: 20
        maxLineLength: 80
    cognitive_complexity:
        function: 15 # after the complexity.neon include
```

Turn a limit off, everywhere or for some paths, with PHPStan's own `ignoreErrors`:

```neon
parameters:
    ignoreErrors:
        -
            identifier: codeLimits.concat
            paths:
                - config/*
```

---

## 🧪 Testing

```bash
composer test
```

---

## Contributing

Contributions are welcome!

- 🐛 **Report bugs** via [GitHub Issues](https://github.com/gboquizosanchez/phpstan-code-limits/issues/new)
- 💡 **Suggest features** or improvements
- 🔧 **Submit pull requests** with fixes or enhancements

---

## Credits

- **Author**: [Germán Boquizo Sánchez](mailto:germanboquizosanchez@gmail.com)

---

## 📄 License

This package is open-source software licensed under the [MIT License](LICENSE.md).
