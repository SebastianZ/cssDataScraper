<?php
  require_once 'PHPDump/src/debug.php';

  $jsonFileName = 'cssData.json';

  class cssData {
    public $properties = [];
    public $syntaxes = [];
    public $atRules = [];
  }

  class cssProperty {
    public $syntax;
    public $media;
    public $shorthand = false;
    public $inherited = false;
    public $animatable = "no";
    public $percentages = "no";
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

  class atRuleDescriptor {
    public $syntax;
    public $media;
  }

  $cssData = new cssData();

  $cssDataURLs = ['CSS_values_syntax', 'CSS_animated_properties', 'CSS_values_serialization', 'CSS_percentage_values'];

  foreach ($cssDataURLs as $cssDataURL) {
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
    $processedResponse = preg_replace(['/<p>.*?<\/p>/', '/<.*?>/', '/^\s*[\r\n]*/m'], '', $processedResponse);

    $group = '';

    foreach(preg_split("/((\r?\n)|(\r\n?))/", $processedResponse) as $line) {
      if (preg_match('/^\{\{/', trim($line)) === 0) {
        $group = trim($line);
      } else if (preg_match('/^{\{cssxref\("([^\/]+?)"\)\}\}$/', trim($line), $matches)) {
        if (!isset($cssData->properties[$matches[1]])) {
          $cssData->properties[$matches[1]] = new cssProperty();
          array_push($cssData->properties[$matches[1]]->groups, $group);
        }
      } else if (preg_match('/^\{\{css(doesinherit|notinherited)\("([^\/]+?)"\)\}\}$/', trim($line), $matches)) {
        if (isset($cssData->properties[$matches[2]])) {
          $cssData->properties[$matches[2]]->inherited = ($matches[1] === 'doesinherit');
        }
      } else if (preg_match('/^\{\{css((?:not)animatable)def\("([^\/]+?)"(?:,\s*"(.+)")?\)\}\}$/', trim($line), $matches)) {
        if (isset($cssData->properties[$matches[2]])) {
          $cssData->properties[$matches[2]]->animatable = ($matches[1] === 'animatable' ? $matches[3] : 'no');
        }
      } else if (preg_match('/^\{\{css((?:no)percentage)def\("([^\/]+?)"(?:,\s*"(.+)")?\)\}\}$/', trim($line), $matches)) {
        if (isset($cssData->properties[$matches[2]])) {
          $cssData->properties[$matches[2]]->percentages = ($matches[1] === 'percentages' ? $matches[3] : 'no');
        }
      } else if (preg_match('/^\{\{cssorderofappearancedef\("(.+?)"\)\}\}$/', trim($line), $matches)) {
        if (isset($cssData->properties[$matches[1]])) {
          $cssData->properties[$matches[1]]->order = 'appearance';
        }
      } else if (preg_match('/^\{\{css(.+?)startdef\("([^\/]+?)"\)\}\}(.*?)\{\{css\1enddef\}\}$/', trim($line), $matches)) {
        if (isset($cssData->properties[$matches[2]])) {
          $cssData->properties[$matches[2]]->{$matches[1]} = $matches[3];
        }
      } else if (preg_match('/^\{\{csssyntaxdef\("([^\/]+?)",\s*"(.+?)",\s*"non-terminal"\)\}\}$/', trim($line), $matches)) {
        if (!isset($cssData->syntaxes[$matches[1]])) {
          $cssData->syntaxes[$matches[1]] = $matches[2];
        }
      } else if (preg_match('/^\{\{css(.+?)def\("([^\/]+?)",\s*"(.+?)"\)\}\}$/', trim($line), $matches)) {
        if (isset($cssData->properties[$matches[2]])) {
          $cssData->properties[$matches[2]]->{$matches[1]} = $matches[3];
        }
      } else if (preg_match('/^\{\{cssinitialshorthand\("([^\/]+?)",\s*"(.+?)"\)\}\}$/', trim($line), $matches)) {
        if (isset($cssData->properties[$matches[1]])) {
          $cssData->properties[$matches[1]]->shorthand = true;
          $cssData->properties[$matches[1]]->longhands = preg_split('/\s+/', $matches[2]);
        }
      }
    } 
  }

  // Add manual data
  $cssData->atRules['@charset'] = new atRule(['Charsets']);
  $cssData->atRules['@counter-style'] = new atRule(['Counter Styles'], ['CSSCounterStyleRule']);

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

  $cssData->atRules['@document'] = new atRule(['Conditional Rules'], ['CSSGroupingRule', 'CSSConditionRule']);
  $cssData->atRules['@font-face'] = new atRule(['Fonts'], ['CSSFontFaceRule']);

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

  $cssData->atRules['@font-feature-values'] = new atRule(['Fonts'], ['CSSFontFeatureValuesRule']);
  $cssData->atRules['@import'] = new atRule(['Media Queries']);
  $cssData->atRules['@keyframes'] = new atRule(['Animations'], ["CSSKeyframeRule", "CSSKeyframesRule"]);
  $cssData->atRules['@media'] = new atRule(["Conditional Rules", "Media Queries"], ["CSSGroupingRule", "CSSConditionRule", "CSSMediaRule", "CSSCustomMediaRule"]);
  $cssData->atRules['@namespace'] = new atRule(['Namespaces']);
  $cssData->atRules['@page'] = new atRule(['Paged Media'], ['CSSPageRule']);

  $descriptor = new atRuleDescriptor();
  $descriptor->syntax = 'auto | &lt;length&gt;';
  $descriptor->initial = 'auto';
  $descriptor->percentages = 'no';
  $descriptor->media = 'all';
  $descriptor->computed = 'as specified';
  $cssData->atRules['@page']->descriptors['bleed'] = $descriptor;

  $cssData->atRules['@supports'] = new atRule(['Conditional Rules'], ["CSSGroupingRule", "CSSConditionRule", "CSSSupportsRule"]);
  $cssData->atRules['@viewport'] = new atRule(['Viewport'], ['CSSViewportRule']);

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

  dump($cssData);

  file_put_contents($jsonFileName, json_encode($cssData, JSON_PRETTY_PRINT));
?>