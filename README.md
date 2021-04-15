# FieldtypeQRCode

A simple fieldtype generating a QR Code from the public URL of the page.

You can also change the text to be anything by hooking after `___getQRText()`:

(in `ready.php`)

```
$wire->addHookAfter("FieldtypeQRCode::getQRText", function($event) {
	$event->return = "Your custom text";
})
```

If you need to display the QR code on the front-end, you can use this:

```
echo $page->getInputfield("name-of-your-field")->render();
```

This fieldtype generates the QR Code using the PHP library [QR Code Generator](https://github.com/kazuhikoarase/qrcode-generator/) by Kazuhiko Arase.

It also extends BaseFieldtypeRuntime, a boilerplate by [Bernhard Baumrock](https://github.com/BernhardBaumrock), and thus is required / needs to be installed. For more informations, check this [Processwire forum post](https://processwire.com/talk/topic/20082-how-to-create-your-very-own-custom-fieldtypes-in-processwire/?tab=comments#comment-174172).