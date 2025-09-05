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
	 */
	protected $recoveryLevel = "L";

	public function __construct() {
		require_once(dirname(__FILE__) . "/qrcode-generator.php");
	}

	public function init() {
		// This is to add cosmetics when outputting to Lister/ListerPro
		$this->addHookAfter("FieldtypeQRCode::markupValue", function(HookEvent $event) {
			$value = $event->arguments(2);
			if(!is_array($value)) {
				$value = str_replace("<img", "<img style=\"margin: 0 0.5em 0.5em 0;\"", $value);
				$event->return = "<div style=\"margin-bottom: -0.5em;\">$value</div>";
			}
		});
	}

	/**
	 * Returns the text to generate the QR code. Defaults to `httpUrl`
	 *
	 * @param Page|Pagefile|Pageimage $page The source Page/Pagefile/Pageimage
	 * @param bool|Language|array $options Specify true to output the page's
	 * `editURL`, a Language object to return URL in that Language or use
	 * $options array:
	 *  - `edit` (bool): Set to `true` to output the page's `editURL`.
	 *  - `language` (Language|bool): Optionally specify Language, or `true` to
	 * force current user language.
	 * @return string
	 *
	 */
	public function ___getQRText($page, $options = []) {
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
			} else if($page instanceof Page && !empty($options["language"])) {
				if($options["language"] === true) {
					$url = $page->localHttpUrl();
				} else {
					$url = $page->httpUrl($options["language"]);
				}
			}
		} else if($page instanceof Page && $options === true) {
			$url = $page->editUrl(true);
			$url = preg_replace("/&language=\d+/", "", $url);
		} else if($page instanceof Page && $options instanceof Language) {
			$url = $page->httpUrl($options);
		}
		return $url;
	}

	/**
	 * Generates the QR code(s) based on the configuration and return them in
	 * an array of arrays containing the QR code image, its original text, its
	 * label and its source (from the configuration)
	 *
	 * @param Page $page
	 * @param array $sources
	 * @param array|null $languages
	 * @return array
	 *
	 */
	public function ___generateQRCodes(Page $page, array $sources = [], $languages = null) {
		$qrcodes = [];

		foreach($sources as $source) {
			if(!is_string($source)) continue;
			if($source === "httpUrl") {
				if(!empty($languages)) {
					$lqr = $this->generateLanguagesQRCodes($page, $source, $languages, "URL");
					$qrcodes = array_merge($qrcodes, $lqr);
					continue;
				}
				$text = $this->getQRText($page);
				$qrcodes[] = $this->generateQRCodeData($text, "URL", $source);
			} elseif($source === "editUrl") {
				$text = $this->getQRText($page, true);
				$qrcodes[] = $this->generateQRCodeData($text, "Admin URL", $source);
			} elseif($f = $page->getUnformatted($source)) {
				$label = $page->fields->get($source)->label;
				if(is_string($f)) {
					$qrcodes[] = $this->generateQRCodeData($f, $label, $source);
				} else if($f instanceof LanguagesPageFieldValue && !empty($languages)) {
					$lqr = $this->generateLanguagesQRCodes($f, $source, $languages, $label);
					$qrcodes = array_merge($qrcodes, $lqr);
				} else if($f instanceof Pagearray) {
					foreach($f as $p) {
						if(!empty($languages)) {
							$lqr = $this->generateLanguagesQRCodes($p, $source, $languages, $label, true);
							$qrcodes = array_merge($qrcodes, $lqr);
							continue;
						}
						$text = $this->getQRText($p);
						$qrcodes[] = $this->generateQRCodeData($text, "{$label}: {$p->title}", $source);
					}
				} else if($f instanceof Pagefiles) {
					foreach($f as $file) {
						$text = $this->getQRText($file);
						$qrcodes[] = $this->generateQRCodeData($text, "{$label}: {$file->basename()}", $source);
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
	 * @param Page|LanguagesPageFieldValue $page
	 * @param string $source
	 * @param array|null $languages
	 * @param string $label
	 * @param bool $addTitle Add the page's title in the label? (default=false)
	 * @return array
	 *
	 */
	public function ___generateLanguagesQRCodes($page, string $source, $languages = null, $label = "", $addTitle = false) {
		if(empty($languages)) return;
		if(!($page instanceof Page) && !($page instanceof LanguagesPageFieldValue)) return;

		$default = $this->wire()->languages->getDefault();
		$root = $this->wire()->pages->get(1);
		$rootUrl = $root->url($default);

		$qrcodes = [];

		foreach($languages as $language) {
			$isDefault = $language->id === $default->id;
			if($page instanceof LanguagesPageFieldValue) {
				$addTitle = false;
				$text = $page->getLanguageValue($language);
				if(!$text) {
					continue;
				}
			} else {
				if(!$isDefault && $rootUrl === $root->url($language)) {
					continue;
				}
				$text = $this->getQRText($page, $language);
			}
			$l = $label;
			if(!$isDefault) {
				$l .= $l ? " ({$language->title})" : $language->title;
			}
			if($addTitle) {
				$l .= $l ? ": $page->title" : $page->title;
			}
			$qrcodes[] = $this->generateQRCodeData($text, $l, $source);
		}

		return $qrcodes;
	}

	protected function generateQRCodeData(string $text, string $label, string $source) {
		$raw = self::generateRawQRCode($text, $this->svg, $this->markup, $this->recoveryLevel);
		$qr = self::generateQRCode($raw, $this->svg, $this->markup, $this->recoveryLevel);
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
	 * @param bool $svg Generate the QR code as svg instead of gif? (default=true)
	 * @param bool $markup If svg, output its markup instead of a base64? (default=false)
	 * @param int $recoveryLevel Allow better data recovery in case of visual damage
	 * (`L` = 7% recovered, `M` = 15%, `Q` = 25%, `H` = 30%)
	 * @return string
	 *
	 */
	public static function generateQRCode(string $text, $svg = true, $markup = false, $recoveryLevel = "L") {
		if(strpos($text, "data:image/") === 0 || strpos($text, "<svg") === 0) {
			$data = $text;
		} else {
			$data = self::generateRawQRCode($text, $svg, $markup, $recoveryLevel);
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
	 * @param bool $svg Generate the QR code as svg instead of gif? (default=true)
	 * @param bool $markup If svg, output its markup instead of a base64? (default=false)
	 * @param int $recoveryLevel Allow better data recovery in case of visual damage
	 * (`L` = 7% recovered, `M` = 15%, `Q` = 25%, `H` = 30%)
	 * @return string
	 *
	 */
	public static function generateRawQRCode(string $text, $svg = true, $markup = false, $recoveryLevel = "L") {
		switch($recoveryLevel) {
			case "L": $recoveryLevel = 1; break;
			case "M": $recoveryLevel = 0; break;
			case "Q": $recoveryLevel = 3; break;
			case "H": $recoveryLevel = 2; break;
		}
		$qr = FieldtypeQRCode\QRCode::getMinimumQRCode($text, $recoveryLevel);

		ob_start();
		if($svg) {
			$qr->printSVG();
		} else {
			$im = $qr->createImage(4, 4);
			imagegif($im);
			imagedestroy($im);
		}
		$data = ob_get_contents();
		ob_end_clean();

		if($svg && !$markup) {
			$data = "data:image/svg+xml;base64," . base64_encode($data);
		} else if(!$svg) {
			$data = "data:image/gif;base64," . base64_encode($data);
		}

		return $data;
	}

	public function ___wakeupValue(Page $page, Field $field, $value) {
		$field = $field->getContext($page);
		$this->svg = $field->get("format") !== "gif";
		$this->markup = $field->get("markup") === 1;
		$this->recoveryLevel = $field->get("recovery");

		if($languages = $page->getLanguages()) {
			$user = $this->user;
			$languages = $user->isGuest() ?	[$user->language] : $languages;
		}
		$sources = $this->parseSources($page, $field);
	
		return $this->generateQRCodes($page, $sources, $languages);
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
		$text->description = $this->_("Define which source(s) you want the QR code(s) to be generated from. You can either use `httpUrl` (`url` will behave the same) and/or `editUrl` and/or the name of any text/URL/file/image field. You can also specify multiple sources by separating them with a comma. Default: `httpUrl`");
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
