<?php namespace Nonoesp\Folio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Folio;
use Thinker;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;
use Spatie\Regex\Regex;
use Illuminate\Support\Arr;

class Item extends Model implements Feedable, Searchable
{
	use \Mpociot\Versionable\VersionableTrait;
	use SoftDeletes;
	use \Conner\Tagging\Taggable;
	use \Spatie\Translatable\HasTranslations;

	/**
	 * @var array
	 */
	public $translatable = ['title', 'text'];

	/**
	 * @var array
	 */
	public $with = ['properties', 'recipients', 'tagged'];

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @var array
	 */
	protected $dates = ['deleted_at'];

	/**
	 * @var boolean
	 */
	protected $softDelete = true;
	
	/**
	 * @var array
	 */	
	protected $dontVersionFields = [
		'title',
		'image',
		'image_src',
		'video',
		'tags_str',
		'slug',
		'slug_title',
		'link',
		'template',
		'visits',
		'recipients_str',
		'rss',
		'is_blog'
	];

	public function __construct() {
	    parent::__construct();
	    $this->table = config('folio.db-prefix').'items';
	}

	public function getSearchResult(): SearchResult
	{
		$url = $this->path();

		return new \Spatie\Searchable\SearchResult(
		   $this,
		   $this->title,
		   $url
		);
	}

	/**
	 * The feed representation of an Item.
	 */
	public function toFeedItem()
    {
        return FeedItem::create()
            ->id($this->id)
            ->title($this->title)
            ->summary('summary')
            ->updated(new \Date($this->published_at))
            ->link('url-here')
            ->author('author');
	}
	
	public function templateView() {

		if (!$this->template) {
            return null;
        }

		$dir = config('folio.templates-path');
		$template_name = str_replace("/","",$this->template);
		if($this->template[0] == '/') {
			$dir = 'folio::template';
		}

		return $dir.'.'.$template_name;
    }

	public function domain() {
		$domain = $this->stringProperty('domain', config('folio.main-domain'));
		if ($domain == null) {
			return \Request::getHttpHost();
		}
		return $domain;
	}

	// The public path of the item
	// (Returns 404 if the post is hidden or scheduled for the future)
	public function path($absolute = false) {
		$path = Folio::path().$this->slug;
		if($this->slug[0] == "/") {
			$path = substr($this->slug, 1, strlen($this->slug)-1);
		}
		if (
			$absolute ||
			$this->domain() != \Request::getHttpHost()
		) {
			$path = '//'.$this->domain().'/'.$path;
		}		
		return $path;
	}

	public function sharePath($absolute = true) {
		$slug = $this->slug;
		if($this->slug[0] == "/") {
			$slug = substr($this->slug, 1, strlen($this->slug)-1);
		}
		$path = 'share/'.$slug;
		if (
			$absolute ||
			$this->domain() != \Request::getHttpHost()
		) {
			$path = '//'.$this->domain().'/'.$path;
		}		
		return $path;
	}

	// The public URL of the item
	public function URL() {
		$protocol = 'http:';
		if (\Request::secure())
		{
			$protocol = 'https:';
		}
		return $protocol.$this->path(true);
	}

	// An encoded path that provides access to hidden items
	public function encodedPath($absolute = false) {
		$path = '/e/'.\Hashids::encode($this->id);
		if($absolute) {
			return $this->domain().$path;
		}
		return $path;
	}

	// The admin path to destroy this item
	public function destroyPath() {
		return '/'.config('folio.admin-path-prefix').'/item/destroy/'.$this->id;
	}

	// The admin path to destroy this item
	public function forceDeletePath() {
		return '/'.config('folio.admin-path-prefix').'/item/force-delete/'.$this->id;
	}	

	// The admin path to edit this item
	public function editPath($absolute = false) {
		$path = '/'.config('folio.admin-path-prefix').'/item/edit/'.$this->id;
		if ($absolute) {
			return $this->domain().$path;
		}
		return $path;
	}

	// The admin path to review the history versions of this item
	public function versionsPath() {
		return '/'.config('folio.admin-path-prefix').'/item/versions/'.$this->id;
	}	

	public function prev() {
			if($prev = Item::where('published_at','<', $this->published_at)->
											 orderBy('published_at', 'DESC')->
											 first()) {
				return $prev;
			}
			return Item::orderBy('published_at', 'DESC')->
									 first();
	}

	public function next() {
			if($next = Item::where('published_at','>', $this->published_at)->
											 orderBy('published_at', 'ASC')->
											 first()) {
				return $next;
			}
			return Item::orderBy('published_at', 'ASC')->
									 first();
	}

	public function prevWithAnyTag($tags, $loop = true) {
			if($prev = Item::withAnyTag($tags)->
							published()->
							where('published_at','<', $this->published_at)->
							orderBy('published_at', 'DESC')->
							first()) {
				return $prev;
			} else if ($loop) {
				return Item::withAnyTag($tags)->
				published()->
				orderBy('published_at', 'DESC')->
				first();				
			}
			return;
	}

	public function nextWithAnyTag($tags, $loop = true) {
			if($next = Item::withAnyTag($tags)->
							published()->
							where('published_at','>', $this->published_at)->
							orderBy('published_at', 'ASC')->
							first()) {
				return $next;
			} else if ($loop) {
				return Item::withAnyTag($tags)->
						published()->
						orderBy('published_at', 'ASC')->
						first();
			}
			return;
	}

	public function recipients()
	{
		return $this->hasMany('Recipient');
	}

	public function properties()
	{
		return $this->hasMany('Property');
	}

    /**
     * Get the Property from an Item if it exists.
	 * Returns the first one if multiple exist.
     *
	 * @param string $key
     * @return \Nonoesp\Folio\Models\Property
     */
	public function property($key) {
		if($property = $this->properties->where('name', $key)->first()) {
				$value = $property->value;
				if($value || $value != '') {
					// property exists and has value
					return $property;
				} else {
					// property exists, but has no value
				}
		} else {
			// property with $key does not exist in database
		}
		return NULL;
	}

    /**
     * Get an array of properties from an Item if they exist.
	 * Returns the first one if multiple exist.
     *
	 * @param string $key
     * @return \Nonoesp\Folio\Models\Property array
     */
	public function propertyArray($key) {

		$properties = $this->properties->where('name', $key);
		if($properties->count()) {
		  return $properties;
		}
		return [];

	}

	public function propertiesWithPrefix($prefix) {

		$matching_properties = [];
		$currentLocale = app()->getLocale();

		foreach($this->properties as $property) {

			// Discard if property is localization, otherwise add
			$parts = explode("--", $property->name);
			$parts_count = count($parts);
			$last_part = $parts[$parts_count - 1];
			if ($parts_count > 1 && \Symfony\Component\Intl\Locales::exists($last_part)) {
				
				// (0) This property is a localization

			} else {

				// Not a localization
				// (1) Find localization or (2) add original property

				if (substr($property->name, 0, strlen($prefix)) === $prefix) {
					$localizedKey = $property->name.'--'.$currentLocale;
					if ($this->hasProperty($localizedKey)) {
						array_push($matching_properties, $this->property($localizedKey));
					} else {
						array_push($matching_properties, $property);
					}
				}
			}

		}

		return $matching_properties;
	}

	// Cast the value of a property to a boolean
	// returns true if property value is 'true', false otherwise
	public function boolProperty($key, $default = false) {
		if($p = $this->property($key)) {
			if($p->value == 'true') {
				return true;
			} else {
				return false;
			}
		}
		return $default;
	}

	public function hasProperty($key) {
		foreach($this->properties as $p) {
			if ($p->name === $key) return true;
		}
		return;
	}

	public function stringProperty($key, $default = null) {

		$currentLocale = app()->getLocale();
		$localizedKey = $key.'--'.$currentLocale;
		$key = $this->hasProperty($localizedKey) ? $localizedKey : $key;

		if($p = $this->property($key)) {
			if($p->value != '') {
				return $p->value;
			}
		}
		return $default;
	}

	public function intProperty($key, $default = null) {
		if($p = $this->property($key)) {
			if ($p->value != '') {
				return intval($p->value);
			}
		}
		return $default;
	}	

	public function scopeBlog($query)
	{
		return $query->where('is_blog', '=', 1);
	}

	public function scopePublic($query)
	{
		return $query->has('recipients', '=', 0);
	}

	public function scopeRSS($query)
	{
		return $query->where('rss', '=', 1);
	}

	public function scopePublished($query)
	{
		return $query->where('published_at', '<', date("Y-m-d H:i:s"));
	}

	public function scopeExpected($query)
	{
		return $query->where('published_at', '>', date("Y-m-d H:i:s"));
	}

	public function scopeVisibleFor($query, $handle)
	{
		$query->public()
    		  ->orWhereHas('recipients', function($q) use ($handle)
    			{
					$q->where("twitter", "=", $handle);
    			});
	}

	// Helpers to Check Visibility of Private Content

	public function visibleFor($twitter_handle) {
		$twitter_handle = strtolower(str_replace("@", "", $twitter_handle));
		if (in_array($twitter_handle, $this->recipientsArray() )) {
			return true;
		} else {
			return false;
		}
	}

	public function recipientsArray() {
		return explode(",", strtolower(str_replace([" ", "@"], "", $this->recipients_str)));
	}

	public function isPublic() {
		return !count($this->recipients);
	}


	public function cardImage($imgixOptions = [], $forceAbsolute = true) {

		// Fallback on images to grab the main thumbnail
		if ($this->stringProperty('card-image')) {
			$thumbnail = $this->imgix($this->stringProperty('card-image'), $imgixOptions);
			
		} else if($this->image) {
			$thumbnail = $this->image($imgixOptions);
		} else if($this->image_src) {
			$thumbnail = $this->image_src($imgixOptions);
		} else if($this->videoThumbnail()) {
			$thumbnail = $this->videoThumbnail();
		} else {
			$thumbnail = null;
		}

		// Make path absolute (add domain) when thumbnail is relative
		if($thumbnail && $forceAbsolute && substr($thumbnail, 0, 1) == '/') {
			return \Request::root().$thumbnail;
		} else if (!$forceAbsolute && count(explode(\Request::root(), $thumbnail)) > 1) {
			return str_replace(\Request::root(), "", $thumbnail);
		}
		return $thumbnail;
	}

	// TODO: Add tests (https://websanova.com/blog/laravel/creating-a-new-package-in-laravel-5-part-5-unit-testing)
	/**
	 * Retrieve the thumbnail corresponding to this image
	 */
	// TODO - change the order of inputs
	public function thumbnail($forceAbsolute = true, $imgixOptions = []) {

		// Fallback on images to grab the main thumbnail
		if($this->image_src) {
			$thumbnail = $this->image_src;
		} else if($this->image) {
			$thumbnail = $this->image;
		} else if($this->videoThumbnail()) {
			$thumbnail = $this->videoThumbnail();
		} else {
			$thumbnail = config('folio.image-src');
		}

		$thumbnail = Item::imgix($thumbnail, $imgixOptions);

		// Make path absolute (add domain) when thumbnail is relative
		if($thumbnail && $forceAbsolute && substr($thumbnail, 0, 1) == '/') {
			return \Request::root().$thumbnail;
		} else if (!$forceAbsolute && count(explode(\Request::root(), $thumbnail)) > 1) {
			return str_replace(\Request::root(), "", $thumbnail);
		} else {
			return $thumbnail;
		}
	}

	/**
	 * Get Item's imgix options.
	 * 
	 * 
	 * @param array $defaultOptions
	 * @param array $overrideOptions
	 * @return array
	 */
	public function imgixOptions($defaultOptions = [], $overrideOptions = []) {
		
		// Start from $defaultOptions
		$imgixOptions = $defaultOptions;

		// Get $itemOptions
		$itemOptions = [
			'w' => $this->intProperty('imgix-w'),
			'h' => $this->intProperty('imgix-h'),
			'q' => $this->intProperty('imgix-q'),
			's' => $this->intProperty('imgix-s'),
			'fit' => $this->stringProperty('imgix-fit'),
		];
		// ..removing empty keys
		foreach ($itemOptions as $key=>$value) {
			if ($value == null) {
				unset($itemOptions[$key]);
			}
		}

		// Inject $itemOptions
		foreach ($itemOptions as $key=>$value) {
			if ($value != null) {
				$imgixOptions[$key] = $value;
			}
		}

		// Inject $overrideOptions
		foreach ($overrideOptions as $key=>$value) {
			if ($value != null) {
				$imgixOptions[$key] = $value;
			}
	}		

		return $imgixOptions;
	}

	public function imgix($imagePath, $imgixOptions = []) {

		// Should we try 
		$imgix_active = $this->boolProperty('imgix', config('folio.imgix'));
		$isRelativePath = substr($imagePath, 0, 1) == '/';

		if ($imgix_active && $isRelativePath) {
			if (count(explode(".gif", $imagePath)) > 1) $imgixOptions = [];
			return imgix($imagePath, $imgixOptions);
		}
		return $imagePath;		
	}

	public function image($imgixOptions = []) {
		return $this->imgix($this->image, $imgixOptions);
	}

	public function image_src($imgixOptions = []) {
		return $this->imgix($this->image_src, $imgixOptions);
	}	

	/**
	 * Returns the URL of the video thumbnail from the provider.
	 */
	public function videoThumbnail() {
		if($customVideoThumbnail = $this->customVideoThumbnail()) {
			return $customVideoThumbnail;
		} else if($providerVideoThumbnail = $this->providerVideoThumbnail()) {
			return $providerVideoThumbnail;
		}
		return null;
	}

	/**
	 * Returns the URL of the video thumbnail from the provider.
	 */
	public function providerVideoThumbnail() {
		if($this->video) {
			return \Thinker::getVideoThumb($this->video);
		}
		return null;
	}

	/**
	 * Returns the URL of the custom video thumbnail specified
	 * on this Item with the custom property video-thumbnail.
	 */	
	public function customVideoThumbnail($forceAbsolute = false) {
		if($videoThumbnail = $this->stringProperty('video-thumbnail')) {
			// Make path absolute (add domain) when thumbnail is relative
			if($videoThumbnail && $forceAbsolute && substr($videoThumbnail, 0, 1) == '/') {
				return \Request::root().$thumbnail;
			}
			if (config('folio.imgix')) {
				return imgix($videoThumbnail);
			}
			return $videoThumbnail;			
		}

		return null;
	}

	/**
	 * Render video as HTML if the Item has a video URL.
	 * (Currently YouTube and Vimeo are supported.)
	 */
	public function renderVideo() {
		if($this->video) {
			return \Thinker::videoWithURL($this->video, 'c-item-v2__cover-media', $this->customVideoThumbnail());
		}
	}

	public static function convertToHtml($text, $markdown_parser = 'default', $veilImages = 'true') {

		if(
			$markdown_parser == 'default' ||
			$markdown_parser == 'commonmark' ||
			$markdown_parser == 'vtalbot'
			) {

				// CommonMark

			// Obtain a pre-configured Environment with all the CommonMark parsers/renderers ready-to-go
			$environment = \League\CommonMark\Environment::createCommonMarkEnvironment();
			// Optional: Add your own parsers, renderers, extensions, etc. (if desired)
			$environment->addExtension(new \Webuni\CommonMark\AttributesExtension\AttributesExtension);
			$environment->addExtension(new \RZ\CommonMark\Ext\Footnote\FootnoteExtension);
			// For example:  $environment->addInlineParser(new TwitterHandleParser());
			// Define your configuration (reference at https://commonmark.thephpleague.com/configuration/):
			$config = ['html_input' => 'allow'];
			// Create the converter
			$converter = new \League\CommonMark\CommonMarkConverter($config, $environment);

			// read and parse markdown
			$html = $converter->convertToHtml($text);

			$html = str_replace(
				["<p><img", "/></p>"],
				["<img",    "/>"],
				$html);

			// Replace image src for veil.gif to then show with unveil.js
			// and save the user from initially loading all images

			if($veilImages) {

				$veilPath = Folio::asset('images/veil.gif');

				$search = [
					// '/<img src="(.*?)" alt="(.*?)" \/>/is',
					'/<img(.*?)src="(.*?)" alt="(.*?)" \/>/is',
				]; 

				$replace = [
						// '<img src="'.$veilPath.'" data-src="$1" alt="$2" />',
						'<img$1src="'.$veilPath.'" data-src="$2" alt="$3" />',
				];

				$html = preg_replace ($search, $replace, $html); 
			}

			// Use imgix?
			if (config('folio.imgix')) {

				$imgixDomain = config('imgix.domain');
				$protocol = config('imgix.useHttps') ? 'https' : 'http';
				$imgixPrefix = $protocol.'://'.$imgixDomain;

				$search = [
					// '/<img src="\/(.*?)" alt="(.*?)" \/>/is',
					'/<img(.*?)src="\/(.*?)" alt="(.*?)" \/>/is',
					// '/<img src="(.*?)" data-src="\/(.*?)" alt="(.*?)" \/>/is',
					'/<img(.*?)src="(.*?)" data-src="\/(.*?)" alt="(.*?)" \/>/is',
					'/<source src="\/(.*?)" type="video\/mp4"(.*?)>/is',
				];

				$replace = [
					// '<img src="'.$imgixPrefix.'/$1" alt="$2" />',
					'<img$1src="'.$imgixPrefix.'/$2" alt="$3" />',
					// '<img src="$1" data-src="'.$imgixPrefix.'/$2" alt="$3" />',
					'<img$1src="$2" data-src="'.$imgixPrefix.'/$3" alt="$4" />',
					'<source src="'.$imgixPrefix.'/$1" type="video/mp4"$2>',
				]; 

				$html = preg_replace ($search, $replace, $html);
			}			
		
		// } else if($markdown_parser == 'vtalbot) {

			// VTalbot

			// Deprecated (at least for now)
			// $html = \VTalbot\Markdown\Facades\Markdown::convertToHtml($text);
			// $html = str_replace(["<p><img","/></p>"],["<img","/>"], $html);

		} else if (
			$markdown_parser == "michelf"
			) {

			// Michelf MarkdownExtra

			$html = \Michelf\MarkdownExtra::defaultTransform($text);
			$html = str_replace(["<p><img","/></p>"],["<img","/>"], $html);

		}

		// Cleanup
		$html = str_replace(
		[
			'<p><nopodcast></p>',
			'<p></nopodcast></p>',
		],
		[
			'',
			'',
		],
		$html);

		return $html;
	}

	/**
	 * Get the Item's Markdown text parsed as HTML with
	 * the correct parser. Each Item can set its own parser
	 * by specifying the custom property 'markdown-parser' to
	 * commonmark, vtalbot, or michelf
	 * 
	 * $options = [
	 * 	veilImages: boolean;
	 *  parseExternalLinks: boolean;
	 *  stripTags: string[];
	 * ]
	 */
	public function htmlTextLegacy($veilImages = true, $parseExternalLinks = false) {
		$markdown_parser = $this->stringProperty('markdown-parser', 'default');
		if($this->hasExcerptTag()) {
			$text = explode(config('folio.excerpt-tag'), $this->text)[1];
			$html = Item::convertToHtml($text, $markdown_parser, $veilImages);
		} else {
            $html = Item::convertToHtml($this->text, $markdown_parser, $veilImages);
        }
        if ($parseExternalLinks) {
            $html = Item::parseExternalLinks($html);
        }
		return $html;
	}

	public function htmlText($options = []) {

		if (!$this->text) {
			return '';
		}

		// Deconstruct options or fallback to default values
		$veilImages = Arr::pull($options, 'veilImages', true);
		$parseExternalLinks = Arr::pull($options, 'parseExternalLinks', false);
		$stripTags = Arr::pull($options, 'stripTags', []);

		// Does the text have an excerpt to trim?
		$text = $this->text;
		if($this->hasExcerptTag()) {
			$text = explode(config('folio.excerpt-tag'), $this->text)[1];
		}
		
		// Strip HTML tags
		// e.g., <norss></norss> <rss></rss> <nopodcast> </nopodcast>
		if (is_array($stripTags) && count($stripTags)) {

			// Remove tags from raw text · $text
			foreach ($stripTags as $tag) {
				$tagPattern = "/<".$tag."[^>]*>(.|\n)*?<\/".$tag.">/";
				if(Regex::match($tagPattern, $text)->hasMatch()) {
					// We wrap with <p></p> to avoid leaving an empty paragraph (because we are stripping
					// the HTML output of Markdown)
					$text = Regex::replace($tagPattern, '', $text)->result();
				}
			}

			// Parse Markdown to HTML · $text → $html
			$markdown_parser = $this->stringProperty('markdown-parser', 'default');
			$html = Item::convertToHtml($text, $markdown_parser, $veilImages);

			// Remove tags from Markdown text · $html
			foreach ($stripTags as $tag) {
				$tagPattern = "/<".$tag."[^>]*>(.|\n)*?<\/".$tag.">/";
				if(Regex::match($tagPattern, $html)->hasMatch()) {
					// We wrap with <p></p> to avoid leaving an empty paragraph (because we are stripping
					// the HTML output of Markdown)
					$html = Regex::replace($tagPattern, '', $html)->result();
				}
			}

		} else {
			// Parse Markdown to HTML
			$markdown_parser = $this->stringProperty('markdown-parser', 'default');
			$html = Item::convertToHtml($text, $markdown_parser, $veilImages);
		}

		// Parse external links
		if ($parseExternalLinks) {
			$html = Item::parseExternalLinks($html);
		}

		return $html;
	}
	
	public function hasExcerpt() {
		return $this->hasMoreTag() || $this->hasExcerptTag();
	}

	public function hasMoreTag() {
		return count(explode(config('folio.more-tag'), $this->text)) > 1;
	}

	public function hasExcerptTag() {
		return count(explode(config('folio.excerpt-tag'), $this->text)) > 1;
	}
	
	public function htmlTextExcerpt($options = []) {

		// Replace item text temporarily if more-tag or excerpt-tag
		
		if($this->hasExcerpt()) {
			// Get more-tag or excerpt-tag
			$tag = config('folio.more-tag');
			if ($this->hasExcerptTag()) {
				$tag = config('folio.excerpt-tag');
			}
			// Get excerpt text
			$textExcerpt = explode($tag, $this->text)[0];
			// Remember actual item text
			$fullItemText = $this->text;
			// Replace temporarily
			$this->text = $textExcerpt;
			$excerptHtml = $this->htmlText($options);
			// Revert to actual item text
			$this->text = $fullItemText;
			// Return excerpt text as HTML
			return $excerptHtml;
		}

		// Fallback to Item full text if no more-tag or excerpt-tag were found
		return $this->htmlText($options);
    }
    
    /**
     * Add target="_blank" and class="is-external" to any links
     * with an href starting on http:// or https://
     */
    public function parseExternalLinks($html) {
        $from = [
            'href="http://',
            'href="https://',
        ];
        $to = [
            'target="_blank" class="is-external" href="http://',
            'target="_blank" class="is-external" href="https://',
        ];
        return str_replace($from, $to, $html);
    }

	/**
	 * Get the Item's permanent link, constructed with
	 * the 'permalink-prefix' from Folio's config and
	 * the id of the Item.
	 */	
	public function permalink() {
		return \Request::root().'/'.Folio::permalinkPrefix().$this->id;
	}

	/**
	 * Get the Item's permanent link for disqus, constructed with
	 * the 'disqus/' prefix plus Item's id.
	 */	
	public function disqusPermalink() {
		return str_replace("https", "http", \Request::root().'/disqus/'.$this->id);
	}

	/**
	 * Returns an array of all existing tag names in all items.
	 */
	public static function existingTagNames() {
		$tagNames = [];
		foreach(Item::existingTags() as $tag) {
			array_push($tagNames, $tag->name);
		}
		return $tagNames;
	}

	/**
	 * Returns an array with an Item's collection tag names.
	 */
	public function collectionTagNames() {
		if($collectionTags = $this->property('collection')) {
			// Get a clean array of lowercase strings of collection tag names
			$collections = explode(",", strtolower(str_replace(', ',',',$collectionTags->value)));
			return $collections;
		}
		return null;
	}

	public function collection() {
		return Item::makeCollection([
			'tags' => $this->stringProperty('collection'),
			'select' => $this->stringProperty('collection-select', '*'),
			'sort' => $this->stringProperty('collection-sort', 'published_at'),
			'order' => $this->stringProperty('collection-order', 'DESC'),
			'limit' => $this->intProperty('collection-limit'),
			'showAll' => $this->boolProperty('collection-show-all'),
			'showHidden' => $this->boolProperty('collection-show-hidden'),
			'showScheduled' => $this->boolProperty('collection-show-scheduled'),
		]);
	}

	/**
	 * Create a collection of items with a query.
	 * @param $params
	 * 	[
	 *		'tags' => 'design, code', // or '*' for wildcard
	 *		'sort' => 'published_at',
	 *		'order' => 'DESC',
	 *		'limit' => 5,
	 *		'showAll' => false, // or true to display all items
	 *	]
	 */
	public static function makeCollection($params) {

		$tags = Arr::get($params, 'tags');
		$select = Arr::get($params, 'select');
		$sort = Arr::get($params, 'sort', 'published_at');
		$order = Arr::get($params, 'order', 'DESC');
		$limit = Arr::get($params, 'limit');
		$showAll = Arr::get($params, 'showAll', false);
		$showHidden = Arr::get($params, 'showHidden', false);
		$showScheduled = Arr::get($params, 'showScheduled', false);
		$collection = [];

		$shouldShowTrashed = false;
		if ($user = \Auth::user()) {
			$shouldShowTrashed = $user->is_admin || $showHidden;
		}

		$published = function($query) { $query->published(); };

		$select = explode(',', $select);

    	if(isset($tags)) {
        	$tagsArray = explode(",", $tags);

          if($tags === "*") {
            
            // Show all items (tag wildcard)
            if($showAll) {
				if($limit) {
					if($shouldShowTrashed) {
						$collection = Item::withTrashed()
											->select($select)
											->when(!$showScheduled, $published) // formerly ->published()
											->orderBy($sort, $order)
											->take($limit)
											->get();
					} else {
						$collection = Item::select($select)
											->when(!$showScheduled, $published) // formerly published()
											->orderBy($sort, $order)
											->take($limit)
											->get();	
					}
				} else {
					if($shouldShowTrashed) {
						$collection = Item::withTrashed()
											->select($select)
											->when(!$showScheduled, $published) // formerly ->published()
											->orderBy($sort, $order)
											->get();
					} else {
						$collection = Item::select($select)
						->when(!$showScheduled, $published) // formerly published()
											->orderBy($sort, $order)
											->get();
					}
				}
            } else {
				if($limit) {
		              $collection = Item::blog()
										  ->select($select)
										  ->when(!$showScheduled, $published) // formerly ->published()
										->orderBy($sort, $order)
										->take($limit)
										->get();					
				} else {
					   $collection = Item::blog()
											  ->select($select)
											  ->when(!$showScheduled, $published) // formerly ->published()
										   ->orderBy($sort, $order)
					   					   ->get();
				}
            }

          } else {

            // Show all items with provided tags
            if($showAll) {
				if($limit) {
						if($shouldShowTrashed) {
							$collection = Item::withTrashed()
											  ->withAnyTag($tagsArray)
											  ->when(!$showScheduled, $published) // formerly ->published()
											  ->orderBy($sort, $order)
											  ->take($limit)
  											  ->get();	
						} else {
							$collection = Item::withAnyTag($tagsArray)
							->when(!$showScheduled, $published) // formerly ->published()
											  ->orderBy($sort, $order)
											  ->take($limit)
											  ->get();	
						}				
				} else {	
					if($shouldShowTrashed) {
						$collection = Item::withTrashed()
										  ->withAnyTag($tagsArray)
										  ->when(!$showScheduled, $published) // formerly ->published()
										  ->orderBy($sort, $order)
										  ->get();			
					} else {
						$collection = Item::withAnyTag($tagsArray)
										  ->when(!$showScheduled, $published) // formerly ->published()
										  ->orderBy($sort, $order)
										  ->get();						
					}			
				}
            } else {
				if($limit) {
		              $collection = Item::withAnyTag($tagsArray)
										->blog()
										->when(!$showScheduled, $published) // formerly ->published()
										->orderBy($sort, $order)
										->take($limit)
										->get();					
				} else {	
              		$collection = Item::withAnyTag($tagsArray)
					  				  ->blog()
									  ->when(!$showScheduled, $published) // formerly ->published()
									  ->orderBy($sort, $order)
									  ->get();
				}
            }

          }
	  }
	  return $collection;
	}

	public function date($format = 'F j, Y') {
		return Item::formatDate($this->published_at, $format);
	}

	public static function formatDate($date, $format = 'F j, Y') {
		return ucWords(\Date::parse($date)->format($format));
	}

	public static function bySlug($slug) {
		if (
			$item = Item::withTrashed()
			->whereSlug($slug)
			->orWhere('slug', '/'.$slug)
			->orWhere('slug', '/'.Folio::path().$slug)
			->first()
		) {
			return $item;
		}
		return null;
	}

	public function description() {
		return $this->summary([
			'limit' => 159,
			'property' => 'meta-description',
		]);
	}

	public function summary($params = ['limit' => 159, 'property' => 'summary']) {

		$limit = Arr::get($params, 'limit', 159);
		$property = Arr::get($params, 'property', 'summary');

        if ($summary = $this->stringProperty($property)) {
            return $summary;
        }
        return Thinker::limitMarkdownText($this->htmlText([
            'stripTags' => ['rss', 'podcast', 'feed']
        ]), $limit , ['sup']);
	}	
	
	/**
	 * Get the estimated reading time of this item's text.
	 */
	public function readTime() {
		return Item::ReadingTime($this->text);
	}

	/**
	 * Get the estimated reading time of an input string.
	 * 
	 * CONFIG
	 * Can be configured by publishing mtownsend/read-time's config files.
	 * php artisan vendor:publish --provider="Mtownsend\ReadTime\Providers\ReadTimeServiceProvider" --tag="read-time-config"
	 * 
	 * LOCALIZE
	 * And localized publishing its translation files.
	 * php artisan vendor:publish --provider="Mtownsend\ReadTime\Providers\ReadTimeServiceProvider" --tag="read-time-language-files"
	 */

	public static function ReadingTime(string $text) {

		$readtime = new \Mtownsend\ReadTime\ReadTime($text);
		$readtime->setTranslation([
			// 'min' => trans('read-time::read-time.min'),
			// 'minute' => trans('read-time::read-time.minute'),
			// 'read' => trans('read-time::read-time.read'),
		]);
		return $readtime->abbreviated()
						->get();
	}
}