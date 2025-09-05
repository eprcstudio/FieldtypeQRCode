# FieldtypeQRCode

A simple fieldtype generating a QR Code from the public URL of the page, and more.

Using the PHP library [QR Code Generator](https://github.com/kazuhikoarase/qrcode-generator/) by Kazuhiko Arase.

![screenshot](https://user-images.githubusercontent.com/6616448/143303398-ffcb4939-1ff4-4877-88c1-0bf7ad662daa.jpg)

Modules directory: [https://processwire.com/modules/fieldtype-qrcode/](https://processwire.com/modules/fieldtype-qrcode/)

Support forum: [https://processwire.com/talk/topic/25676-fieldtypeqrcode](https://processwire.com/talk/topic/25676-fieldtypeqrcode)

## Options

In the field’s `Details` tab you can change between .gif or .svg formats. If you select .svg
you will have the option to directly output the markup instead of a base64 image. SVG is the default.

You can also change what is used to generate the QR code and even have several sources. The accepted sources (separated by a comma) are: `httpUrl`, `editUrl`, or the name of any text/URL/file/image field.

If `LanguageSupport` is installed the compatible sources (`httpUrl`, text field, ...) will return as many QR codes as there are languages. Note however that when outputting on the front-end, only the languages visible to the user will be generated.

Additionally you can set the error correction level which allows to better recover lost data in case of visual damage. [This is also used when covering part of a QR code with a logo](https://en.wikipedia.org/wiki/QR_code#Error_correction). There are four levels of correction:

-   `L`, with 7% of potential data recovery
-   `M`, with 15% of potential data recovery
-   `Q`, with 25% of potential data recovery
-   and `H`, with 30% of potential data recovery

## Formatting

### Unformatted value

When using `$page->getUnformatted("qrcode_field")` it returns an array with the following structure:

```php
[
	[
		"label" => string,  // label used in the admin
		"qr" => string,     // the qrcode image
		"raw" => string,    // the raw qrcode image (in base64, except if svg+markup)
		"source" => string, // the source, as defined in the configuration
		"text" => string    // and the text used to generate the qrcode
	],
	...
]
```

### Formatted value

The formatted value is an `<img>`/`<svg>` (or several right next to each other). There is no other markup.

Should you need the same markup as in the admin you could use:

```php
$field = $fields->get("qrcode_field");
$field->type->markupValue($page, $field, $page->getUnformatted("qrcode_field"));
```

But it’s a bit cumbersome, plus you need to import the FieldtypeQRCode's css/js. Best is to make your own markup using the unformatted value.

## Static QR code generator

You can call `FieldtypeQRCode::generateQRCode` to generate any QR code you want. Its arguments are:

```
string $text
bool   $svg           Generate the QR code as svg instead of gif ? (default=true)
bool   $markup        If svg, output its markup instead of a base64 ? (default=false)
string $recoveryLevel Set error correction level (default="L")
```

## Hooks

Please have a look at the source code for more details about the hookable functions.

### Examples

```php
$wire->addHookAfter("FieldtypeQRCode::getQRText", function($event) {
	$page = $event->arguments("page");
	$event->return = $page->title;
	// or could be: $event->return = "Your custom text";
})
```

```php
$wire->addHookAfter("FieldtypeQRCode::generateQRCodes", function($event) {
	$qrcodes = $event->return;
	// keep everything except the QR codes generated from editUrl
	foreach($qrcodes as $key => &$qrcode) {
		if($qrcode["source"] === "editUrl") {
			unset($qrcodes[$key]);
		}
	}
	unset($qrcode);
	$event->return = $qrcodes;
})
```

## Note

Depending on the level of correction set and the type of characters encoded in the QR code, [the maximum size allowed for a QR code can vary](https://en.wikipedia.org/wiki/QR_code#Information_capacity). It is adviced to set a maximum character count on textareas or any relevant Inputfields

| Recovery Level | Numeric | Alphanumeric | Byte | Kanji |
| -------------- | ------- | ------------ | ---- | ----- |
| L (7%)         | 7089    | 4296         | 2953 | 1817  |
| M (15%)        | 5596    | 3391         | 2331 | 1435  |
| Q (25%)        | 3993    | 2420         | 1663 | 1024  |
| H (30%)        | 3057    | 1852         | 1273 | 784   |
