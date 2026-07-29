<?php namespace ProcessWire;

/**
 * Fieldtype generating a QR code from the public URL of the page (and more)
 *
 * Copyright (c) 2025 Romain Cazier
 * Licensed under MIT License, see LICENSE
 *
 * https://eprc.studio
 *
 * For ProcessWire 3.x
 * Copyright (c) 2021 by Ryan Cramer
 * Licensed under GNU/GPL v2
 *
 * https://www.processwire.com
 *
 * QRCode-Generator library by Kazuhiko Arase
 * https://github.com/kazuhikoarase/qrcode-generator/
 *
 * @method string getQRText($page, Field $field, $options = []) Returns the text to generate the QR code. Defaults to `httpUrl`
 * @method array generateQRCodes(Page $page, Field $field, array $sources = [], $languages = null) Generates the QR code(s)
 * @method array generateLanguagesQRCodes($page, Field $field, string $source, $languages = null, $fromPage = null) Generates the QR code(s) in each languages
 * 
 */
class FieldtypeQRCode extends Fieldtype {

	/**
	 * Output as svg instead of gif (default=true)
	 * 
	 * @var bool
	 * 
	 */
	protected $svg = true;
	
	/**
	 * Output svg markup instead of base64 (default=false)
	 * 
	 * @var bool
	 * 
	 */
	protected $markup = false;

	/**
	 * Level of error correction / data recovery for damaged QR codes
	 * 
	 * Level L: 7% recovery
	 * Level M: 15% recovery
	 * Level Q: 25% recovery
	 * Level H: 30% recovery
	 * 
	 * @var string
	 * 
	 */
	protected $recoveryLevel = "L";

	/**
	 * Module’s size (default=2)
	 * 
	 * @var int
	 * 
	 */
	protected $size = 2;

	/**
	 * Foreground color (default="#000000")
	 * 
	 * @var string
	 * 
	 */
	protected $foreground = "#000000";

	/**
	 * Background color (default="#FFFFFF")
	 * 
	 * @var string
	 * 
	 */
	protected $background = "#FFFFFF";

	/**
	 * Should the QR code be transparent? (default=false)
	 * 
	 * This overrides the background color
	 * 
	 * @var string
	 * 
	 */
	protected $transparent = false;

	public function init() {
		// This is to add cosmetics when outputting to Lister/ListerPro
		$this->addHookAfter("FieldtypeQRCode::markupValue", function(HookEvent $event) {
			$value = $event->arguments(2);
			if(!is_array($value)) {
				$value = str_replace(
					"<img",
					"<img style=\"margin: 0 0.5em 0.5em 0;\"",
					$value
				);
				$event->return = "<div style=\"margin-bottom: -0.5em;\">$value</div>";
			}
		});
	}

	/**
	 * Returns the text to generate the QR code. Defaults to `httpUrl`
	 *
	 * @param Page|Pagefile|Pageimage $page The source Page/Pagefile/Pageimage
	 * @param Field $field The field generating the QR code
	 * @param bool|Language|array $options Specify true to output the page's
	 * `editURL`, a Language object to return URL in that Language or use
	 * $options array:
	 *  - `edit` (bool): Set to `true` to output the page's `editURL`.
	 *  - `language` (Language|bool): Optionally specify Language, or `true` to
	 * force current user language.
	 * @return string
	 *
	 */
	public function ___getQRText($page, Field $field, $options = []) {
		$url = $page->httpUrl;
		if(is_array($options)) {
			if($page instanceof Page && !empty($options["edit"])) {
				if(empty($options["language"])) {
					$url = $page->editUrl(true);
					$url = preg_replace("/&language=\d+/", "", $url);
				} else {
					$url = $page->editUrl([
						"http" => true,
						"language" => $options["language"]
					]);
				}
			} elseif($page instanceof Page && !empty($options["language"])) {
				if($options["language"] === true) {
					$url = $page->localHttpUrl();
				} else {
					$url = $page->httpUrl($options["language"]);
				}
			}
		} elseif($page instanceof Page && $options === true) {
			$url = $page->editUrl(true);
			$url = preg_replace("/&language=\d+/", "", $url);
		} elseif($page instanceof Page && $options instanceof Language) {
			$url = $page->httpUrl($options);
		}
		return $url;
	}

	/**
	 * Generates the QR code(s) based on the configuration and return them in
	 * an array of arrays containing the QR code image, its original text, its
	 * label and its source (from the configuration)
	 *
	 * @param Page $page The source Page
	 * @param Field $field The field generating the QR code
	 * @param array $sources The different sources to generate the QR codes from
	 * @param PageArray|null $languages
	 * @return array
	 *
	 */
	public function ___generateQRCodes(
		Page $page,
		Field $field,
		array $sources = [],
		$languages = null
	) {
		$qrcodes = [];

		foreach($sources as $source) {
			if(!is_string($source)) continue;
			if($source === "httpUrl") {
				if(!empty($languages)) {
					$lqr = $this->generateLanguagesQRCodes(
						$page,
						$field,
						$source,
						$languages,
					);
					$qrcodes = array_merge($qrcodes, $lqr);
					continue;
				}
				$qrcodes[] = $this->generateQRCodeData(
					$this->getQRText($page, $field),
					"URL",
					$source
				);
			} elseif($source === "editUrl") {
				$qrcodes[] = $this->generateQRCodeData(
					$this->getQRText($page, $field, true),
					"Admin URL",
					$source
				);
			} elseif($value = $page->getUnformatted($source)) {
				$isSubfield = strpos($source, ".") !== false;
				if($isSubfield) {
					$split = explode(".", $source);
					$label = $page->fields->get($split[0])->label;
					$label .= " ($split[1])";
 				} else {
					$label = $page->fields->get($source)->label;
				}
				if(is_string($value)) {
					$qrcodes[] = $this->generateQRCodeData($value, $label, $source);
				} elseif($value instanceof LanguagesPageFieldValue && !empty($languages)) {
					$lqr = $this->generateLanguagesQRCodes(
						$value,
						$field,
						$source,
						$languages,
						$page
					);
					$qrcodes = array_merge($qrcodes, $lqr);
				} elseif($value instanceof Pagearray) {
					foreach($value as $subpage) {
						if(!empty($languages)) {
							$lqr = $this->generateLanguagesQRCodes(
								$subpage,
								$field,
								$source,
								$languages,
								$page
							);
							$qrcodes = array_merge($qrcodes, $lqr);
							continue;
						}
						$qrcodes[] = $this->generateQRCodeData(
							$this->getQRText($subpage, $field),
							"{$label}: {$subpage->title}",
							$source
						);
					}
				} elseif($value instanceof Pagefiles) {
					foreach($value as $file) {
						$qrcodes[] = $this->generateQRCodeData(
							$this->getQRText($file, $field),
							"{$label}: {$file->basename()}",
							$source
						);
					}
				} elseif(is_array($value)) {
					foreach($value as $v) {
						$qrcodes[] = $this->generateQRCodeData(
							$v,
							$label,
							$source
						);
					}
				}
			}
		}

		return $qrcodes;
	}

	/**
	 * Generates the QR code(s) for the page (or multi-languages field) in each
	 * languages
	 *
	 * @param Page|LanguagesPageFieldValue $page The source Page
	 * @param Field $field The field generating the QR code
	 * @param string $source The different sources to generate the QR codes from
	 * @param PageArray|null $languages
	 * @param Page|null $fromPage
	 * @return array
	 *
	 */
	public function ___generateLanguagesQRCodes(
		$page,
		Field $field,
		string $source,
		$languages = null,
		$fromPage = null,
	) {
		if(empty($languages)) return;
		if(
			!($page instanceof Page)
			&& !($page instanceof LanguagesPageFieldValue)
		) return;

		if(empty($fromPage) && $page instanceof Page) {
			$fromPage = $page;
		}

		$sourceField = $fromPage ? $fromPage->fields->get($source) : false;
		$isPageField = $sourceField instanceof PageField;

		$defaultLang = $this->wire()->languages->getDefault();
		$root = $this->wire()->pages->get(1);
		$rootUrl = $root->url($defaultLang);
		$userLang = $this->wire()->user->language;

		$qrcodes = [];

		foreach($languages as $language) {
			$isDefault = $language->id === $defaultLang->id;
			if($page instanceof LanguagesPageFieldValue) {
				$text = $page->getLanguageValue($language);
				if(!$text) {
					continue;
				}
			} else {
				if(!$isDefault && $rootUrl === $root->url($language)) {
					continue;
				}
				$text = $this->getQRText($page, $field, $language);
			}
			if($source === "httpUrl" || $isPageField) {
				$label = "URL";
			} else {
				$label = $sourceField
					? $sourceField->getLabel($userLang)
					: $source;
			}
			if($isPageField) {
				$label .= " ({$sourceField->getLabel($userLang)})";
			}
			if(!$isDefault) {
				$label .= " ({$language->title})";
			}
			if($page instanceof Page && $isPageField) {
				$label .= ": {$page->getLanguageValue($language, "title")}";
			}
			$qrcodes[] = $this->generateQRCodeData($text, $label, $source);
		}

		return $qrcodes;
	}

	protected function generateQRCodeData(
		string $text,
		string $label,
		string $source,
	) {
		$options = [
			"svg" => $this->svg,
			"markup" => $this->markup,
			"recoveryLevel" => $this->recoveryLevel,
			"size" => $this->size,
			"foreground" => $this->foreground,
			"background" => $this->background,
			"transparent" => $this->transparent
		];
		$raw = self::generateRawQRCode($text, $options);
		$qr = self::generateQRCode($raw, $options);
		$qr = str_replace("<img", "<img alt=\"$text\"", $qr);
		return [
			"label" => $label,
			"qr" => $qr,
			"raw" => $raw,
			"source" => $source,
			"text" => $text,
		];
	}

	/**
	 * Generates a QR code as an <img> or <svg>
	 *
	 * @param string $text
	 * @param bool|array{svg:bool,markup:bool,recoveryLevel:string,size:int,foreground:string,background:string,transparent:bool} $svg Generate the QR code as svg instead of gif? (default=true). If an array is specified instead of a boolean, it can be used to set the different options and appearance settings. Foreground/background colors must be CSS hexadecimal values (e.g. "#000000")
	 * @param bool $markup If svg, output its markup instead of a base64? (default=false)
	 * @param int $recoveryLevel Allow better data recovery in case of visual damage
	 * (`L` = 7% recovered, `M` = 15%, `Q` = 25%, `H` = 30%)
	 * @return string
	 *
	 */
	public static function generateQRCode(
		string $text, 
		bool|array $svg = true,
		$markup = false,
		$recoveryLevel = "L"
	) {
		$options = [];
		if(is_array($svg)) {
			$options = $svg;
			$svg = $options["svg"] ?? true;
			$markup = $options["markup"] ?? false;
		} else {
			$options["svg"] = $svg;
			$options["markup"] = $markup;
			$options["recoveryLevel"] = $recoveryLevel;
		}

		if(strpos($text, "data:image/") === 0 || strpos($text, "<svg") === 0) {
			$data = $text;
		} else {
			$data = self::generateRawQRCode($text, $options);
		}

		if($svg && $markup) {
			$qr = str_replace("svg\">", "svg\"><title>$text</title>", $data);
		} else {
			$qr = "<img src=\"$data\" />";
		}
		
		return $qr;
	}

	/**
	 * Generates a QR code as a base64 of a gif or svg (or leave intact if we
	 * want the markup)
	 *
	 * @param string $text
	 * @param bool|array{svg:bool,markup:bool,recoveryLevel:string,size:int,foreground:string,background:string,transparent:bool} $svg Generate the QR code as svg instead of gif? (default=true). If an array is specified instead of a boolean, it can be used to set the different options and appearance settings. Foreground/background colors must be CSS hexadecimal values (e.g. "#000000")
	 * @param bool $markup If svg, output its markup instead of a base64? (default=false)
	 * @param int $recoveryLevel Allow better data recovery in case of visual damage
	 * (`L` = 7% recovered, `M` = 15%, `Q` = 25%, `H` = 30%)
	 * @return string
	 *
	 */
	public static function generateRawQRCode(
		string $text,
		bool|array $svg = true,
		$markup = false,
		$recoveryLevel = "L"
	) {
		if(!class_exists("ProcessWire\FieldtypeQRCode\QRCode")) {
			require_once(dirname(__FILE__) . "/qrcode-generator.php");
		}

		$options = [
			"svg" => true,
			"markup" => false,
			"recoveryLevel" => "L",
			"size" => 2,
			"foreground" => "#000000",
			"background" => "#FFFFFF",
			"transparent" => false,
		];
		if(is_array($svg)) {
			$options = array_merge($options, $svg);
		} else {
			$options["svg"] = $svg;
			$options["markup"] = $markup;
			$options["recoveryLevel"] = $recoveryLevel;
		}

		if(!preg_match("/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/", $options["foreground"])) {
			$options["foreground"] = "#000000";
		}

		if(!preg_match("/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/", $options["background"])) {
			$options["background"] = "#FFFFFF";
		}

		switch($options["recoveryLevel"]) {
			case "M": $errorCorrectionLevel = 0; break;
			case "Q": $errorCorrectionLevel = 3; break;
			case "H": $errorCorrectionLevel = 2; break;
			default: $errorCorrectionLevel = 1; break;
		}
		$qr = FieldtypeQRCode\QRCode::getMinimumQRCode($text, $errorCorrectionLevel);

		ob_start();
		if($options["svg"]) {
			$qr->printSVG(
				$options["size"],
				$options["foreground"],
				$options["background"],
				$options["transparent"]
			);
		} else {
			$im = $qr->createImage(
				$options["size"],
				0,
				hexdec(str_replace("#", "", $options["foreground"])),
				hexdec(str_replace("#", "", $options["background"])),
				$options["transparent"]
			);
			imagegif($im);
			if (PHP_MAJOR_VERSION < 8) {
				imagedestroy($im);
			}
		}
		$data = ob_get_contents();
		ob_end_clean();

		if($options["svg"] && !$options["markup"]) {
			$data = "data:image/svg+xml;base64," . base64_encode($data);
		} elseif(!$options["svg"]) {
			$data = "data:image/gif;base64," . base64_encode($data);
		}

		return $data;
	}

	public function ___wakeupValue(Page $page, Field $field, $value) {
		$field = $field->getContext($page);
		$this->svg = $field->get("format") !== "gif";
		$this->markup = $field->get("markup") === 1;
		$this->recoveryLevel = $field->get("recovery") ?? "L";
		$this->size = (int) ($field->get("size") ?? 2);
		$this->foreground = $field->get("foreground") ?? "#000000";
		$this->background = $field->get("background") ?? "#FFFFFF";
		$this->transparent = $field->get("transparent") === 1;

		if($languages = $page->getLanguages()) {
			$user = $this->user;
			$languages = $user->isGuest() ?	[$user->language] : $languages;
		}
		$sources = $this->parseSources($page, $field);
	
		return $this->generateQRCodes($page, $field, $sources, $languages);
	}

	/**
	 * Parses the sources from the configuration and return them in an array
	 *
	 * @param Field $field
	 * @return array
	 *
	 */
	protected function parseSources(Page $page, Field $field) {
		if(!($sources = $field->get("source"))) return ["httpUrl"];

		$sources = explode(",", $sources);
		foreach($sources as &$source) {
			$source = trim($source);
			if($source === "url") $source = "httpUrl";
		}
		unset($source);

		return array_unique($sources);
	}

	public function ___formatValue(Page $page, Field $field, $value) {
		/** @var array $value */
		return array_reduce($value, array($this, "reduceQRCodes"));
	}

	protected function reduceQRCodes($carry, $item) {
		$carry .= $item["qr"];
		return $carry;
	}

	public function ___markupValue(Page $page, Field $field, $value = null, $property = "") {
		if(!is_array($value)) {
			return $value;
		} elseif(!count($value)) {
			$out = "<p>";
			$out .= $this->_("No QR code to output");
			$out .= ".<br>";
			$out .= sprintf(
				$this->_('Please check your %1$ssource(s)%2$s'),
				"<a href=\"{$field->editUrl()}#fieldtypeConfig\" target=\"_blank\">",
				"</a>"
			);
			$out .= ".</p>";
			return $out;
		} else {
			$out = "";
			if(count($value) > 1) {
				$options = [];
				foreach($value as $v) {
					$options[] = $v["label"];
				}
				$out .= $this->renderSelectOptions($options);
			}
			$out .= "<ul class=\"gridQRCodes\">";
			$firstQR = true;
			foreach($value as $v) {
				$out .= "<li class=\"gridQRCode";
				if($firstQR === true) {
					$firstQR = $v["text"];
					$out .= " show";
				}
				$out .= "\">";
				$out .= $v["qr"];
				$out .= "</li>";
			}
			$out .= "</ul>";
			$out .= "<p class=\"contentQRCode\">";
			if(
				strpos($firstQR, "http") === 0
				|| strpos($firstQR, "mailto") === 0
				|| strpos($firstQR, "tel") === 0
			) {
				$out .= "<a href=\"$firstQR\" target=\"_blank\">";
			}
			$out .= $firstQR;
			if(
				strpos($firstQR, "http") === 0
				|| strpos($firstQR, "mailto") === 0
				|| strpos($firstQR, "tel") === 0
			) {
				$out .= "</a>";
			}
			$out .= "</p>";
			return $out;
		}
	}

	protected function renderSelectOptions(array $options) {
		$out = "<select class=\"uk-select {$this->className}Select\">";
		foreach($options as $key => $label) {
			$out .= "<option value=\"$key\">$label</option>";
		}
		$out .= "</select>";
		return $out;
	}

	/**
	 * TODO: add get function to allow to return a specific source / language
	 *
	 */
	public function get($key) {
		return parent::get($key);
	}

	public function ___getConfigInputfields(Field $field) {
		if(is_null($field->get("format"))) $field->set("format", "svg");
		if(is_null($field->get("markup"))) $field->set("markup", 0);
		if(is_null($field->get("source"))) $field->set("source", "");
		if(is_null($field->get("recovery"))) $field->set("recovery", "L");
		if(is_null($field->get("size"))) $field->set("size", 2);
		if(is_null($field->get("foreground"))) $field->set("foreground", "#000000");
		if(is_null($field->get("background"))) $field->set("background", "#FFFFFF");
		if(is_null($field->get("transparent"))) $field->set("transparent", false);

		$modules = $this->wire()->modules;

		$inputfields = parent::___getConfigInputfields($field);

		/** @var InputfieldRadios $radios */
		$radios = $modules->get("InputfieldRadios");
		$radios->attr("name", "format");
		$radios->columnWidth = 50;
		$radios->description = $this->_("Allows to select the image format of the QR code");
		$radios->icon = "file";
		$radios->label = $this->_("Format");
		$radios->optionColumns = 1;
		$radios->value = $field->get("format");
		$radios->addOptions(["svg" => ".svg", "gif" => ".gif"]);
		$inputfields->add($radios);

		/** @var InputfieldCheckbox $checkbox */
		$checkbox = $modules->get("InputfieldCheckbox");
		$checkbox->attr("name", "markup");
		$checkbox->columnWidth = 50;
		$checkbox->description = $this->_("Allows to render the SVG markup directly, instead of a base64 image");
		$checkbox->icon = "code";
		$checkbox->label = $this->_("Render SVG Markup?");
		$checkbox->label2 = $this->_("Yes");
		$checkbox->showIf("format=svg");
		$checkbox->value = $field->get("markup");
		if($field->get("markup") === 1) {
			$checkbox->checked(true);
		}
		$inputfields->add($checkbox);

		/** @var InputfieldText $text */
		$text = $modules->get("InputfieldText");
		$text->attr("name", "source");
		$text->description = $this->_("Defines which source(s) you want the QR code(s) to be generated from. You can either use `httpUrl` (`url` will behave the same) and/or `editUrl` and/or the name of any text/URL/file/image field. You can also specify multiple sources by separating them with a comma. Default: `httpUrl`");
		$text->icon = "database";
		$text->label = $this->_("QR code source(s)");
		$text->value = $field->get("source");
		$inputfields->add($text);

		/** @var InputfieldSelect $select */
		$select = $modules->get("InputfieldSelect");
		$select->attr("name", "recovery");
		$select->columnWidth = 100;
		$select->description = $this->_("Allows to recover different levels of lost data if the QR code is visually damaged. Please note this reduces the maximum size of the QR code");
		$select->icon = "qrcode";
		$select->label = $this->_("Set the error correction level");
		$select->value = $field->get("recovery");
		$select->addOptions([
			"L" => "Level L: 7% of data can be restored", 
			"M" => "Level M: 15% of data can be restored",
			"Q" => "Level Q: 25% of data can be restored",
			"H" => "Level H: 30% of data can be restored"
		]);
		$inputfields->add($select);

		/** @var InputfieldText $text */
		$markup = $modules->get("InputfieldMarkup");
		$markup->collapsed = Inputfield::collapsedYes;
		$markup->description = $this->_("Depending on the level of correction set and the type of characters encoded in the QR code, [the maximum size allowed for a QR code can vary](https://en.wikipedia.org/wiki/QR_code#Information_capacity). It is adviced to set a maximum character count on textareas or any relevant Inputfields");
		$markup->icon = "exclamation-triangle";
		$markup->label = $this->_("Maximum QR code size");
		/** @var MarkupAdminDataTable $table */
		$table = $modules->get("MarkupAdminDataTable");
		$table->setResponsive(false);
		$table->setSortable(false);
		$table->headerRow([
			"Recovery Level", "Numeric", "Alphanumeric", "Byte", "Kanji"
		]);
		$table->row(["L (7%)", 7089, 4296, 2953, 1817]);
		$table->row(["M (15%)", 5596, 3391, 2331, 1435]);
		$table->row(["Q (25%)", 3993, 2420, 1663, 1024]);
		$table->row(["H (30%)", 3057, 1852, 1273, 784]);
		$markup->value = $table->render();
		$inputfields->add($markup);

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get("InputfieldFieldset");
		$fieldset->label = $this->_("Appearance");
		$fieldset->icon = "brush";

		/** @var InputfieldInteger $integer */
		$integer = $modules->get("InputfieldInteger");
		$integer->attr("name", "size");
		$integer->columnWidth = 25;
		$integer->description = $this->_("Defines the size of one dot (in pixels)");
		$integer->label = $this->_("Module size");
		$integer->value = $field->get("size");
		$fieldset->add($integer);

		/** @var InputfieldColor|InputfieldText $foreground */
		$foreground = $modules->get("InputfieldColor") ?? $modules->get("InputfieldText");
		$foreground->attr("name", "foreground");
		$foreground->columnWidth = 25;
		$foreground->description = $this->_("Please use CSS hexadecimal values, e.g. #000000");
		$foreground->label = $this->_("Foreground color");
		$foreground->value = $field->get("foreground");
		$fieldset->add($foreground);

		/** @var InputfieldColor|InputfieldText $background */
		$background = $modules->get("InputfieldColor") ?? $modules->get("InputfieldText");
		$background->attr("name", "background");
		$background->columnWidth = 25;
		$background->description = $this->_("Please use CSS hexadecimal values, e.g. #FFFFFF");
		$background->label = $this->_("Background color");
		$background->value = $field->get("background");
		$fieldset->add($background);

		/** @var InputfieldCheckbox $checkbox */
		$checkbox = $modules->get("InputfieldCheckbox");
		$checkbox->attr("name", "transparent");
		$checkbox->columnWidth = 25;
		$checkbox->description = $this->_("This option overrides the background color");
		$checkbox->icon = "code";
		$checkbox->label = $this->_("Use a transparent background?");
		$checkbox->label2 = $this->_("Yes");
		$checkbox->value = $field->get("transparent");
		if($field->get("transparent") === 1) {
			$checkbox->checked(true);
		}
		$fieldset->add($checkbox);

		$inputfields->add($fieldset);

		return $inputfields;
	}

	public function ___getConfigAllowContext(Field $field) {
		$fields = ["format", "markup", "source", "recovery"];
		return array_merge(parent::___getConfigAllowContext($field), $fields);
	}

	public function getInputfield(Page $page, Field $field) {
		$config = $this->wire()->config;
		$config->styles->add($config->urls->{$this} . "{$this}.css");
		$config->scripts->add($config->urls->{$this} . "{$this}.js");

		/** @var InputfieldMarkup $inputfield */
		$inputfield = $this->wire()->modules->get("InputfieldMarkup");

		$inputfield->addHookBefore("render", function(HookEvent $event) use($page, $field) {
			$event->replace = true;
			$event->return = $this->markupValue($page, $field, $event->object->value);
		});

		$inputfield->addHookAfter("getConfigInputfields", function(HookEvent $event) {
			$inputfields = $event->return;
			$options = [
				Inputfield::collapsedNo,
				Inputfield::collapsedYes,
				Inputfield::collapsedNever,
			];
			$collapsed = $inputfields->get("collapsed");
			foreach(array_keys($collapsed->getOptions()) as $option) {
				if(in_array($option, $options)) continue;
				$collapsed->removeOption($option);
			}
		});

		return $inputfield;
	}

	public function upgrade($fromVersion, $toVersion) {
		if(
			version_compare($fromVersion, "2.0.0", "<")
			&& version_compare($toVersion, "2.0.0", ">=")
		) {
			$this->warning(
				sprintf(
					$this->_("This version introduces a breaking change related to the module’s hookable methods, please have a look at the [support forum](%s) to learn more"),
					"https://processwire.com/talk/topic/25676-fieldtypeqrcode"
				),
				Notice::allowMarkdown
			);
		}
	}

	/* Keep FieldtypeQRCode out of the database */

	public function getCompatibleFieldtypes(Field $field) {
		return $this->wire(new Fieldtypes());
	}

	public function getMatchQuery($query, $table, $subfield, $operator, $value) {
		throw new WireException(sprintf($this->_('Field "%s" is runtime only and not queryable'), $query->field->name));
	}

	public function getLoadQueryAutojoin(Field $field, DatabaseQuerySelect $query) {
		return null;
	}

	public function sanitizeValue(Page $page, Field $field, $value) {
		return $value;
	}

	public function sleepValue(Page $page, Field $field, $value) {
		return $value;
	}

	public function savePageField(Page $page, Field $field) {
		return true;
	}

	public function loadPageField(Page $page, Field $field) {
		return "";
	}

	public function getLoadQuery(Field $field, DatabaseQuerySelect $query) {
		return $query;
	}

	public function deletePageField(Page $page, Field $field) {
		return true;
	}

	public function createField(Field $field) {
		return true;
	}

	public function deleteField(Field $field) {
		return true;
	}

	public function getDatabaseSchema(Field $field) {
		return [];
	}
}
