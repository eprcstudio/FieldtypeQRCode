$(document).ready(function () {
	$(".FieldtypeQRCodeSelect").change(function () {
		const $this = $(this);

		// ul.gridQRCodes > .show
		$this.next().children(".show").removeClass("show");

		// ul.gridQRCodes > :nth-child(index)
		const $qrcode = $this.next().children().eq(this.selectedIndex);
		$qrcode.addClass("show");

		// li.gridQRCode > *
		const $qrcodeImage = $qrcode.children().first();
		let url;
		if ($qrcodeImage.is("svg")) {
			url = $qrcodeImage.children("title").html();
		} else {
			url = $qrcodeImage.attr("alt");
		}

		// p.contentQRCode
		if (
			url.indexOf("http") === 0
			|| url.indexOf("mailto") === 0
			|| url.indexOf("tel") === 0
		) {
			url = `<a href="${url}" target=\"_blank\">${url}</a>`;
		}
		$this.next().next().html(url);
	});
});
