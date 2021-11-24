# FieldtypeQRCode

A simple fieldtype generating a QR Code from the public URL of the page, and more.

Using the PHP library [QR Code Generator](https://github.com/kazuhikoarase/qrcode-generator/) by Kazuhiko Arase.

## Options

In the field’s `Details` tab you can change between .gif or .svg formats. If you select .svg
you will have the option to directly output the markup instead of a base64 image. SVG is the default.

You can also change what is used to generate the QR code and even have several sources. The accepted sources (separated by a comma) are: `httpUrl`, `editUrl`, or the name of a file/image field.

If `LanguageSupport` is installed the source `httpUrl` will return as many QR codes as there are languages. Note however that when outputting on the front-end, only the languages visible to the user will be generated.

## Formatting

### Unformatted value

When using `$page->getUnformatted("qrcode_field")` it returns an array with the following structure:

```php
[
	[
		"label" => string,  // label used in the admin
		"qr" => string,     // the qrcode image
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
bool   $svg    Generate the QR code as svg instead of gif ? (default=true)
bool   $markup If svg, output its markup instead of a base64 ? (default=false)
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