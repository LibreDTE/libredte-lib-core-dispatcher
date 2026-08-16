LibreDTE: Dispatcher para la Biblioteca PHP (Core)
===================================================

Conector que expone las operaciones de `libredte/libredte-lib-core` de forma
segura y agnóstica al transporte, usando [`derafu/backbone-dispatcher`](https://github.com/derafu/backbone-dispatcher)
como base. Pensado como la capa intermedia entre LibreDTE y un bridge hacia
otro lenguaje, aunque no depende de ninguno en particular.

Uso
---

```php
use libredte\lib\CoreDispatcher\Bootstrap;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;

$dispatcher = Bootstrap::boot(); // SafeDispatcherInterface

$request = new OperationRequest('billing', 'identifier', 'caf_loader', 'load', [
    'xml' => base64_encode($cafXml),
]);

$result = $dispatcher->dispatch($request); // Nunca lanza excepciones.

if ($result->isSuccess()) {
    $caf = $result->getValue(); // Ya serializado (array), listo para cruzar
                                 // un límite de proceso/lenguaje.
} else {
    $problem = $result->getProblem(); // ProblemDetail (RFC 7807-like).
}
```

Arquitectura
------------

- `Bootstrap::boot(string $environment = 'prod', bool $debug = false)` es el
  único punto de entrada público. Todo el resto del cableado (Deserializers,
  la cadena `DirectDispatcher` → `TypedDispatcher` → `SafeDispatcher` de
  `derafu/backbone-dispatcher`) está declarado en `config/services.yaml`, no
  en PHP.
- `config/services.yaml` importa el de `libredte-lib-core` y sobreescribe
  `libredte.lib.core.project_dir`: `kernel.project_dir` se resuelve contra el
  paquete que actúa como punto de entrada, y ese rol lo cumple este paquete,
  no `libredte-lib-core`.
- `Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface` está registrado
  `lazy: true` porque `DocumentBagDeserializer`/`SiiRequestDeserializer`
  necesitan ese mismo registro (para deserializar sus campos anidados) y a
  la vez son entradas de su propio mapa de deserializers — una referencia
  circular que `lazy: true` rompe.

Deserializers
-------------

Los 9 Deserializers de este paquete cubren únicamente interfaces/tipos de
`libredte-lib-core` (o de paquetes Derafu genéricos que Core usa):
`DocumentBagInterface`, `XmlDocumentInterface`, `CafInterface`,
`CertificateInterface`, `EmisorInterface`, `ReceptorInterface`,
`MandatarioInterface`, `DocumentEnvelopeInterface`, `SiiRequestInterface`.

Para agregar soporte a una clase nueva:

1. Revisar si la clase ya tiene un `fromArray(array $data): self` estático.
   Si lo tiene, no hace falta escribir nada: `FromArrayDeserializer` (el
   `$fallback` del registro) ya la cubre automáticamente, sin registrar
   nada en `services.yaml`.
2. Si no lo tiene, ver si ya existe una Factory a la que delegar (ver
   `EmisorDeserializer`, `ReceptorDeserializer`, `MandatarioDeserializer`).
3. Si no hay Factory, construir el objeto directamente, delegando los
   campos que a su vez son objetos en el `ObjectFactoryInterface` inyectado
   (ver `DocumentBagDeserializer`).
4. Si la construcción no tiene una única forma fija, decidir según qué
   claves están presentes (ver `CertificateDeserializer`, que elige entre
   `loadFromData()`/`loadFromKeys()`).
5. Todo Deserializer empieza narrowing su entrada con `assertArray()`/
   `assertString()` (heredados de `Abstract\AbstractDeserializer`).

`DocumentBag` no tiene Deserializer propio para sus campos `document` y
`documentType`: siempre reciben `null`. Agregar los suyos cuando se
necesiten.

Desarrollo
----------

```bash
composer install
composer tests    # PHPUnit con cobertura
composer phpstan  # Nivel 5
composer phpcs    # php-cs-fixer (dry-run)
```

Términos y condiciones de uso
------------------------------

Licenciado bajo AGPL-3.0+. Disponibles en archivo [COPYING](COPYING)
