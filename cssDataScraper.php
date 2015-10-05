<?php
  if (file_exists('PHPDump/src/debug.php')) {
    require_once 'PHPDump/src/debug.php';
  }

  $jsonFileName = 'cssData.json';
  $ordersToSet = [];

  class cssData {
    public $properties = [];
    public $syntaxes = [];
    public $atRules = [];
    public $selectors = [];
  }

  class cssProperty {
    public $syntax;
    public $media;
    public $shorthand = false;
    public $inherited = false;
    public $animatable = 'no';
    public $percentages = 'no';
    public $groups = [];
  }

  class atRule {
    public function __construct($groups, $interfaces=[]) {
      if (!empty($interfaces)) {
        $this->interfaces = $interfaces;
      }
      $this->groups = $groups;
    }
  }

  class selector {
    public $syntax;
    public $groups = [];
  }

  class atRuleDescriptor {
    public $syntax;
    public $media;
  }


  function parseValuesSyntax($cssData, $line) {
    if (preg_match('/^\{\{css(doesinherit|notinherited)\("([^\/]+?)"\)\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[2]])) {
        $cssData->properties[$matches[2]]->inherited = ($matches[1] === 'doesinherit');
      }
    } else if (preg_match('/^\{\{csssyntaxdef\("([^\/]+?)",\s*"(.+?)",\s*"non-terminal(?:-cont)?"\)\}\}$/', $line, $matches)) {
      if (!isset($cssData->syntaxes[$matches[1]])) {
        $cssData->syntaxes[$matches[1]] = $matches[2];
      }
    } else if (preg_match('/^\{\{cssinitialshorthand\("([^\/]+?)",\s*"(.+?)"\)\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[1]])) {
        $cssData->properties[$matches[1]]->shorthand = true;
        $cssData->properties[$matches[1]]->longhands = preg_split('/\s+/', $matches[2]);
      }
    } else if (preg_match('/^\{\{cssinitialdef\("([^\/]+?)",\s*"(.+?)"\)\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[1]])) {
        $cssData->properties[$matches[1]]->initial = '<code>' . $matches[2] . '</code>';
      }
    } else {
    	return false;
    }

    return true;
  }


  function parseAnimatedProperties($cssData, $line, $property) {
    if (preg_match('/^\{\{css((?:not)?animatable)def\("([^\/]+?)"(?:,\s*"(.+?)")?(?:,\s*"(.+?)")?\)\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[2]])) {
        $animatable = 'no';
        if ($matches[1] === 'animatable') {
          $animatable = isset($matches[3]) ? $matches[3] : 'yes';
        }
        if (isset($matches[4])) {
          $animatable .= $matches[4];
        }
        $cssData->properties[$matches[2]]->animatable = $animatable;
      }
    } else if (preg_match('/^\{\{cssanimatableshorthand\("([^\/]+?)",\s*"(.+?)"\)\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[1]])) {
        $cssData->properties[$matches[1]]->animatable = preg_split('/\s+/', $matches[2]);
      }
    } else if ($line !== '' && preg_match('/\{|</', $line)) {
    	$cssData->properties[$property]->animatable = $line;
    } else {
      return false;
    }

    return true;
  }


  function parseValuesSerialization($cssData, $line) {
  	global $ordersToSet;

    if (preg_match('/^\{\{csscomputedcolordef\("([^\/]+?)"\)\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[1]])) {
        $cssData->properties[$matches[1]]->computed = 'color';
      }
    } else if (preg_match('/^\{\{cssorderstartdef\("(.+?)"\)\}\}\{\{cssorder\("(.+?)"\)\}\}/', $line, $matches)) {
      if (isset($cssData->properties[$matches[1]])) {
      	if (!isset($cssData->properties[$matches[2]]->order)) {
      		$ordersToSet[$matches[1]] = $matches[2];
      	} else {
          $cssData->properties[$matches[1]]->order = $cssData->properties[$matches[2]]->order;
      	}
      }
    } else if (preg_match('/^\{\{cssorderofappearancedef\("(.+?)"\)\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[1]])) {
        $cssData->properties[$matches[1]]->order = 'appearance';
      }
    } else {
    	return false;
    }

    return true;
  }


  function parsePercentageValues($cssData, $line) {
    if (preg_match('/^\{\{css((?:no)?percentage)def\("([^\/]+?)"(?:,\s*"(.+)")?\)\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[2]])) {
        $cssData->properties[$matches[2]]->percentages = ($matches[1] === 'percentage' ? $matches[3] : 'no');
      }
    } else if (preg_match('/^\{\{csspercentagestartdef\("([^\/]+?)"\)\}\}(.*?)\{\{csspercentageenddef\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[1]])) {
        $cssData->properties[$matches[1]]->percentages = $matches[2];
      }
    } else if (preg_match('/^\{\{csspercentageshorthand\("([^\/]+?)",\s*"(.+?)"\)\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[1]])) {
        $cssData->properties[$matches[1]]->percentages = preg_split('/\s+/', $matches[2]);
      }
    } else {
    	return false;
    }

    return true;
  }


  function parseSpecialProperties($cssData, $line) {
    if (preg_match('/^\{\{css((?:no)?stacking)\("(.+?)"\)\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[2]]) && $matches[1] === 'stacking') {
        $cssData->properties[$matches[2]]->stacking = true;
      }
    } else if (preg_match('/^\{\{css(not)?on(.+?)\("(.+?)"\)\}\}$/', $line, $matches)) {
      if (isset($cssData->properties[$matches[3]]) && $matches[1] === '') {
        if (!isset($cssData->properties[$matches[3]]->alsoAppliesTo)) {
          $cssData->properties[$matches[3]]->alsoAppliesTo = [];
        }
        array_push($cssData->properties[$matches[3]]->alsoAppliesTo, $matches[2]);
      }
    } else {
    	return false;
    }

    return true;
  }


  function removeTags($matches) {
    if (isset($matches[1]) && ($matches[1] === 'code' || $matches[1] === 'a')) {
      return $matches[0];
    }

    return '';
  }


  function mapGroup($group) {
    switch ($group) {
      case 'Color':
        return 'CSS Colors';

      case 'Text decorations':
        return 'CSS Text Decoration';

      case 'Writing modes':
        return 'CSS Writing Modes';

      case 'Flexible boxes':
        return 'CSS Flexible Box Layout';

      case 'Background &amp; Borders':
        return 'CSS Background and Borders';

      case 'Counters &amp; Lists':
        return 'CSS Lists and Counters';

      case 'Page':
        return 'CSS Pages';

      case 'User interface':
        return 'CSS User Interface';

      case 'Generated content':
        return 'CSS Generated Content';

      case 'Filter Effects':
        return 'Filter Effects';

      case 'Compositing and Blending':
        return 'Compositing and Blending';

      case 'Pointer Events':
        return 'Pointer Events';

      case 'CSSOM View':
        return 'CSSOM View';

      case 'Counter Styles':
        return 'CSS Lists and Counters';

      case 'Media Queries':
        return 'Media Queries';
    }
    return 'CSS ' . $group;
  }

  $cssData = new cssData();

  // Add sub-syntaxes, which are not listed within the info pages
  $cssData->syntaxes['color'] = '&lt;rgb()&gt; | &lt;rgba()&gt; | &lt;hsl()&gt; | &lt;hsla()&gt; | &lt;hex-color&gt; | &lt;named-color&gt; | currentcolor | &lt;deprecated-system-color&gt;';
  $cssData->syntaxes['rgb()'] = 'rgb( &lt;rgb-component&gt;#{3} )';
  $cssData->syntaxes['rgba()'] = 'rgba( &lt;rgb-component&gt;#{3} , &lt;alpha-value&gt; )';
  $cssData->syntaxes['rgb-component'] = '&lt;integer&gt; | &lt;percentage&gt;';
  $cssData->syntaxes['alpha-value'] = '&lt;number&gt;';
  $cssData->syntaxes['hsl()'] = 'hsl( &lt;hue&gt;, &lt;percentage&gt;, &lt;percentage&gt; )';
  $cssData->syntaxes['hsla()'] = 'hsla( &lt;hue&gt;, &lt;percentage&gt;, &lt;percentage&gt;, &lt;alpha-value&gt; )';
  $cssData->syntaxes['hue'] = '&lt;number&gt;';
  $cssData->syntaxes['named-color'] = '&lt;ident&gt;';
  $cssData->syntaxes['deprecated-system-color'] = 'ActiveBorder | ActiveCaption | AppWorkspace | Background | ButtonFace | ButtonHighlight | ButtonShadow | ButtonText | CaptionText | GrayText | Highlight | HighlightText | InactiveBorder | InactiveCaption | InactiveCaptionText | InfoBackground | InfoText | Menu | MenuText | Scrollbar | ThreeDDarkShadow | ThreeDFace | ThreeDHighlight | ThreeDLightShadow | ThreeDShadow | Window | WindowFrame | WindowText';

  $cssDataURLs = [
      'CSS_values_syntax' => 'parseValuesSyntax',
      'CSS_animated_properties' => 'parseAnimatedProperties',
      'CSS_values_serialization' => 'parseValuesSerialization',
      'CSS_percentage_values' => 'parsePercentageValues',
      'CSS_special_properties' => 'parseSpecialProperties'
  ];

  foreach ($cssDataURLs as $cssDataURL => $parsingFunction) {
    $fileName = $cssDataURL . '.html';
    if (isset($_GET['refresh']) || !file_exists($fileName)) {
      $fetchLocation = 'https://developer.mozilla.org/en-US/docs/Web/CSS/' . $cssDataURL . '?raw';
    } else {
      $fetchLocation = $fileName;
    }
  
    $response = file_get_contents($fetchLocation);
  
    if (isset($_GET['refresh']) || !file_exists($fileName)) {
      file_put_contents($fileName, $response);
    }
  
    $processedResponse = $response;
    $columnNames = ['Property', 'Syntax', 'Initial value', 'Inherited', 'Media', 'Animatable', 'Applies to', 'Computed value', 'Canonical order',
        'Percentage values'
    ];
    foreach($columnNames as $columnName) {
      $processedResponse = str_ireplace('<th scope="col">' . $columnName . '</th>', '', $processedResponse);
    }
    $processedResponse = preg_replace_callback(['/<p>.*?<\/p>/', '/<\/?([^\s>]*).*?>/', '/^\s*[\r\n]*/m'], 'removeTags', $processedResponse);

    $group = '';
    $property = '';

    foreach(preg_split("/((\r?\n)|(\r\n?))/", $processedResponse) as $line) {
      $line = trim($line);
      if (preg_match('/^\{\{/', $line) === 0 && preg_match('/^[A-Z]/', $line) !== 0) {
        $group = $line;
      } else if (preg_match('/^{\{cssxref\("([^\/]+?)"\)\}\}$/', $line, $matches)) {
        $property = $matches[1];
        if (!isset($cssData->properties[$matches[1]])) {
          $cssData->properties[$matches[1]] = new cssProperty();
          array_push($cssData->properties[$matches[1]]->groups, mapGroup($group));
        }
      } else if (!$parsingFunction($cssData, $line, $property)) {
        if (preg_match('/^\{\{css(.+?)startdef\("([^\/]+?)"\)\}\}(.*?)\{\{css\1enddef\}\}$/', $line, $matches)) {
	        if (isset($cssData->properties[$matches[2]])) {
	          $cssData->properties[$matches[2]]->{$matches[1]} = $matches[3];
	        }
	      } else if (preg_match('/^\{\{css(.+?)def\("([^\/]+?)",\s*"(.+?)"\)\}\}$/', $line, $matches)) {
	        if (isset($cssData->properties[$matches[2]])) {
	          $cssData->properties[$matches[2]]->{$matches[1]} = $matches[3];
	        }
	      }
      }

      if (in_array($property, $ordersToSet) && isset($cssData->properties[$property]->order)) {
      	$cssData->properties[array_search($property, $ordersToSet)]->order = $cssData->properties[$property]->order;
      }
    }
  }

  // Add manual data
  $cssData->atRules['@charset'] = new atRule(['CSS Charsets']);
  $cssData->atRules['@counter-style'] = new atRule(['CSS Lists and Counters'], ['CSSCounterStyleRule']);

  $cssData->atRules['@counter-style']->descriptors = [];

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'cyclic | numeric | alphabetic | symbolic | additive | [fixed &lt;integer&gt;?] | [ extends &lt;counter-style-name&gt; ]';
  $descriptor->initial = 'symbolic';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@counter-style']->descriptors['system'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;symbol&gt; &lt;symbol&gt;?';
  $descriptor->initial = '\"-\" hyphen-minus';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@counter-style']->descriptors['negative'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;symbol&gt;';
  $descriptor->initial = 'the empty string';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@counter-style']->descriptors['prefix'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;symbol&gt;';
  $descriptor->initial = '\".\" full stop followed by a space';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@counter-style']->descriptors['suffix'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '[ [ &lt;integer&gt; | infinite ]{2} ]# | auto';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@counter-style']->descriptors['range'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;integer&gt; &amp;&amp; &lt;symbol&gt;';
  $descriptor->initial = '0 \"\"';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@counter-style']->descriptors['pad'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;counter-style-name&gt;';
  $descriptor->initial = 'decimal';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@counter-style']->descriptors['fallback'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;symbol&gt;+';
  $descriptor->initial = 'N/A';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@counter-style']->descriptors['symbols'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '[ &lt;integer&gt; &amp;&amp; &lt;symbol&gt; ]#';
  $descriptor->initial = 'N/A';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@counter-style']->descriptors['additive-symbols'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'auto | bullets | numbers | words | spell-out | &lt;counter-style-name&gt;';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@counter-style']->descriptors['speak-as'] = $descriptor;

  $cssData->atRules['@document'] = new atRule(['CSS Conditional Rules'], ['CSSGroupingRule', 'CSSConditionRule']);
  $cssData->atRules['@font-face'] = new atRule(['CSS Fonts'], ['CSSFontFaceRule']);

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;family-name&gt;';
  $descriptor->initial = 'n/a (required)';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@font-face']->descriptors['font-family'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '[ &lt;url&gt; format(&lt;string&gt;#)? | local(&lt;family-name&gt;) ]#';
  $descriptor->initial = 'n/a (required)';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@font-face']->descriptors['src'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'normal | italic | oblique';
  $descriptor->initial = 'normal';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@font-face']->descriptors['font-style'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'normal | bold | 100 | 200 | 300 | 400 | 500 | 600 | 700 | 800 | 900';
  $descriptor->initial = 'normal';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@font-face']->descriptors['font-weight'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'normal | ultra-condensed | extra-condensed | condensed | semi-condensed | semi-expanded | expanded | extra-expanded | ultra-expanded';
  $descriptor->initial = 'normal';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@font-face']->descriptors['font-stretch'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;unicode-range&gt;#';
  $descriptor->initial = 'U+0-10FFFF';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@font-face']->descriptors['unicode-range'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'normal | none | [ &lt;common-lig-values&gt; || &lt;discretionary-lig-values&gt; || &lt;historical-lig-values&gt; || &lt;contextual-alt-values&gt; || stylistic(&lt;feature-value-name&gt;) || historical-forms || styleset(&lt;feature-value-name&gt;#) || character-variant(&lt;feature-value-name&gt;#) || swash(&lt;feature-value-name&gt;) || ornaments(&lt;feature-value-name&gt;) || annotation(&lt;feature-value-name&gt;) || [ small-caps | all-small-caps | petite-caps | all-petite-caps | unicase | titling-caps ] || &lt;numeric-figure-values&gt; || &lt;numeric-spacing-values&gt; || &lt;numeric-fraction-values&gt; || ordinal || slashed-zero || &lt;east-asian-variant-values&gt; || &lt;east-asian-width-values&gt; || ruby ]';
  $descriptor->initial = 'normal';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@font-face']->descriptors['font-variant'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'normal | &lt;feature-tag-value&gt;#';
  $descriptor->initial = 'normal';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@font-face']->descriptors['font-feature-settings'] = $descriptor;

  $cssData->atRules['@font-feature-values'] = new atRule(['CSS Fonts'], ['CSSFontFeatureValuesRule']);
  $cssData->atRules['@import'] = new atRule(['Media Queries']);
  $cssData->atRules['@keyframes'] = new atRule(['CSS Animations'], ["CSSKeyframeRule", "CSSKeyframesRule"]);
  $cssData->atRules['@media'] = new atRule(["CSS Conditional Rules", "Media Queries"], ["CSSGroupingRule", "CSSConditionRule", "CSSMediaRule", "CSSCustomMediaRule"]);
  $cssData->atRules['@namespace'] = new atRule(['CSS Namespaces']);
  $cssData->atRules['@page'] = new atRule(['CSS Pages'], ['CSSPageRule']);

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'none | [ crop || cross ]';
  $descriptor->initial = 'none';
  $descriptor->percentages = 'no';
  $descriptor->media = 'visual, paged';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@page']->descriptors['marks'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'auto | &lt;length&gt;';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'no';
  $descriptor->media = 'visual, paged';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@page']->descriptors['bleed'] = $descriptor;

  $cssData->atRules['@supports'] = new atRule(['CSS Conditional Rules'], ["CSSGroupingRule", "CSSConditionRule", "CSSSupportsRule"]);
  $cssData->atRules['@viewport'] = new atRule(['CSS Device Adaptation'], ['CSSViewportRule']);

  $cssData->atRules['@viewport']->descriptors = [];

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;viewport-length&gt;';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'refer to the width of the initial viewport';
  $descriptor->media = 'visual, continuous';
  $descriptor->computed = 'auto, an absolute length, or a percentage as specified';
  $cssData->atRules['@viewport']->descriptors['min-width'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;viewport-length&gt;';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'refer to the width of the initial viewport';
  $descriptor->media = 'visual, continuous';
  $descriptor->computed = 'auto, an absolute length, or a percentage as specified';
  $cssData->atRules['@viewport']->descriptors['max-width'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;viewport-length&gt;{1,2}';
  $descriptor->media = 'visual, continuous';
  $descriptor->longhands = ['min-width', 'max-width'];
  $cssData->atRules['@viewport']->descriptors['width'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;viewport-length&gt;';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'refer to the height of the initial viewport';
  $descriptor->media = 'visual, continuous';
  $descriptor->computed = 'auto, an absolute length, or a percentage as specified';
  $cssData->atRules['@viewport']->descriptors['min-height'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;viewport-length&gt;';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'refer to the height of the initial viewport';
  $descriptor->media = 'visual, continuous';
  $descriptor->computed = 'auto, an absolute length, or a percentage as specified';
  $cssData->atRules['@viewport']->descriptors['max-height'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = '&lt;viewport-length&gt;{1,2}';
  $descriptor->media = 'visual, continuous';
  $descriptor->longhands = ['min-height', 'max-height'];
  $cssData->atRules['@viewport']->descriptors['height'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'auto | &lt;number&gt; | &lt;percentage&gt;';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'the zoom factor itself';
  $descriptor->media = 'visual, continuous';
  $descriptor->computed = 'auto, or a non-negative number or percentage as specified';
  $cssData->atRules['@viewport']->descriptors['zoom'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'auto | &lt;number&gt; | &lt;percentage&gt;';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'the zoom factor itself';
  $descriptor->media = 'visual, continuous';
  $descriptor->computed = 'auto, or a non-negative number or percentage as specified';
  $cssData->atRules['@viewport']->descriptors['min-zoom'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'auto | &lt;number&gt; | &lt;percentage&gt;';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'the zoom factor itself';
  $descriptor->media = 'visual, continuous';
  $descriptor->computed = 'auto, or a non-negative number or percentage as specified';
  $cssData->atRules['@viewport']->descriptors['max-zoom'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'zoom | fixed';
  $descriptor->initial = 'zoom';
  $descriptor->percentages = 'refer to the size of bounding box';
  $descriptor->media = 'visual, continuous';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@viewport']->descriptors['user-zoom'] = $descriptor;

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'auto | portrait | landscape';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'refer to the size of bounding box';
  $descriptor->media = 'visual, continuous';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@viewport']->descriptors['orientation'] = $descriptor;

  // Selectors
  $selector = new selector();
  $selector->syntax = 'element';
  $selector->groups = ['Basic Selectors'];
  $cssData->selectors['Type selectors'] = $selector;

  $selector = new selector();
  $selector->syntax = '.class';
  $selector->groups = ['Basic Selectors'];
  $cssData->selectors['Class selectors'] = $selector;

  $selector = new selector();
  $selector->syntax = '#id';
  $selector->groups = ['Basic Selectors'];
  $cssData->selectors['ID selectors'] = $selector;

  $selector = new selector();
  $selector->syntax = '*';
  $selector->groups = ['Basic Selectors'];
  $cssData->selectors['Universal selectors'] = $selector;

  $selector = new selector();
  $selector->syntax = '[attr=value]';
  $selector->groups = ['Basic Selectors'];
  $cssData->selectors['Attribute selectors'] = $selector;

  $selector = new selector();
  $selector->syntax = 'A + B';
  $selector->groups = ['Combinators'];
  $cssData->selectors['Adjacent sibling selectors'] = $selector;

  $selector = new selector();
  $selector->syntax = 'A ~ B';
  $selector->groups = ['Combinators'];
  $cssData->selectors['General sibling selectors'] = $selector;

  $selector = new selector();
  $selector->syntax = 'A &gt; B';
  $selector->groups = ['Combinators'];
  $cssData->selectors['Child selectors'] = $selector;

  $selector = new selector();
  $selector->syntax = 'A B';
  $selector->groups = ['Combinators'];
  $cssData->selectors['Descendant selectors'] = $selector;

  $selector = new selector();
  $selector->syntax = ':active';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':active'] = $selector;

  $selector = new selector();
  $selector->syntax = ':any';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':any'] = $selector;

  $selector = new selector();
  $selector->syntax = ':checked';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':checked'] = $selector;

  $selector = new selector();
  $selector->syntax = ':dir()';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':dir'] = $selector;

  $selector = new selector();
  $selector->syntax = ':disabled';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':disabled'] = $selector;

  $selector = new selector();
  $selector->syntax = ':empty';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':empty'] = $selector;

  $selector = new selector();
  $selector->syntax = ':enabled';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':enabled'] = $selector;

  $selector = new selector();
  $selector->syntax = ':first';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':first'] = $selector;

  $selector = new selector();
  $selector->syntax = ':first-child';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':first-child'] = $selector;

  $selector = new selector();
  $selector->syntax = ':first-of-type';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':first-of-type'] = $selector;

  $selector = new selector();
  $selector->syntax = ':fullscreen';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':fullscreen'] = $selector;

  $selector = new selector();
  $selector->syntax = ':focus';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':focus'] = $selector;

  $selector = new selector();
  $selector->syntax = ':hover';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':hover'] = $selector;

  $selector = new selector();
  $selector->syntax = ':indeterminate';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':indeterminate'] = $selector;

  $selector = new selector();
  $selector->syntax = ':in-range';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':in-range'] = $selector;

  $selector = new selector();
  $selector->syntax = ':invalid';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':invalid'] = $selector;

  $selector = new selector();
  $selector->syntax = ':lang()';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':lang'] = $selector;

  $selector = new selector();
  $selector->syntax = ':last-child';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':last-child'] = $selector;

  $selector = new selector();
  $selector->syntax = ':last-of-type';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':last-of-type'] = $selector;

  $selector = new selector();
  $selector->syntax = ':left';
  $selector->groups = ['Pseudo-classes', 'CSS Pages'];
  $cssData->selectors[':left'] = $selector;

  $selector = new selector();
  $selector->syntax = ':link';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':link'] = $selector;

  $selector = new selector();
  $selector->syntax = ':not()';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':not'] = $selector;

  $selector = new selector();
  $selector->syntax = ':nth-child()';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':nth-child'] = $selector;

  $selector = new selector();
  $selector->syntax = ':nth-last-child()';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':nth-last-child'] = $selector;

  $selector = new selector();
  $selector->syntax = ':nth-last-of-type()';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':nth-last-of-type'] = $selector;

  $selector = new selector();
  $selector->syntax = ':nth-of-type()';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':nth-of-type'] = $selector;

  $selector = new selector();
  $selector->syntax = ':only-child';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':only-child'] = $selector;

  $selector = new selector();
  $selector->syntax = ':only-of-type';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':only-of-type'] = $selector;

  $selector = new selector();
  $selector->syntax = ':optional';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':optional'] = $selector;

  $selector = new selector();
  $selector->syntax = ':out-of-range';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':out-of-range'] = $selector;

  $selector = new selector();
  $selector->syntax = ':active';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':read-only'] = $selector;

  $selector = new selector();
  $selector->syntax = ':read-write';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':read-write'] = $selector;

  $selector = new selector();
  $selector->syntax = ':required';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':required'] = $selector;

  $selector = new selector();
  $selector->syntax = ':right';
  $selector->groups = ['Pseudo-classes', 'CSS Pages'];
  $cssData->selectors[':right'] = $selector;

  $selector = new selector();
  $selector->syntax = ':root';
  $selector->groups = ['Pseudo-classes', 'CSS Pages'];
  $cssData->selectors[':root'] = $selector;
  
  $selector = new selector();
  $selector->syntax = ':scope';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':scope'] = $selector;

  $selector = new selector();
  $selector->syntax = ':target';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':target'] = $selector;

  $selector = new selector();
  $selector->syntax = ':valid';
  $selector->groups = ['Pseudo-classes'];
  $cssData->selectors[':valid'] = $selector;

  $selector = new selector();
  $selector->syntax = '::after';
  $selector->groups = ['Pseudo-elements'];
  $cssData->selectors['::after'] = $selector;

  $selector = new selector();
  $selector->syntax = '::before';
  $selector->groups = ['Pseudo-elements'];
  $cssData->selectors['::before'] = $selector;

  $selector = new selector();
  $selector->syntax = '::first-letter';
  $selector->groups = ['Pseudo-elements'];
  $cssData->selectors['::first-letter'] = $selector;

  $selector = new selector();
  $selector->syntax = '::first-line';
  $selector->groups = ['Pseudo-elements'];
  $cssData->selectors['::first-line'] = $selector;

  $selector = new selector();
  $selector->syntax = '::selection';
  $selector->groups = ['Pseudo-elements'];
  $cssData->selectors['::selection'] = $selector;

  $selector = new selector();
  $selector->syntax = '::backdrop';
  $selector->groups = ['Pseudo-elements'];
  $cssData->selectors['::backdrop'] = $selector;

  if (function_exists('dump')) {
    dump($cssData);
  } else {
    var_dump($cssData);
  }

  file_put_contents($jsonFileName, json_encode($cssData, JSON_PRETTY_PRINT));
?>