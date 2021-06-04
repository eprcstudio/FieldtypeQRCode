# FieldtypeQRCode

A simple fieldtype generating a QR Code from the public URL of the page.

Using the PHP library [QR Code Generator](https://github.com/kazuhikoarase/qrcode-generator/) by Kazuhiko Arase.

## Options

In the field's `Details` tab you can change between .gif or .svg formats. If you select .svg
you will have the option to directly output the markup instead of a base64 image.
## Modify QR Code content

You can eventually change the QR Code content to be anything by hooking after `___getQRText()`:

```
$wire->addHookAfter("FieldtypeQRCode::getQRText", function($event) {
	$event->return = "Your custom text";
})
```