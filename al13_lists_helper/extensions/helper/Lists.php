<?php
/**
 * Lists helper 0.3
 *
 * @copyright     Copyright 2010, alkemann
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 *
 */
namespace al13_lists_helper\extensions\helper;

use lithium\net\http\Router;

/**
 * The purpose of this helper is to generate menus and other lists of links. The dynamic api
 * lets you build any amount of multi level "menus". Created for the purpose of main, sub and
 * context sensitive menues, this helper can also be used as an HTML `<ul />` generator.
 *
 * Installation and requirements:
 *
 * - Add the AL13_helpers library to your app by placing the `al13_helpers` folder either in your
 *   `/lithium/libraries` folder
 *   or in your `/app/libraries/` folder, or somewhere else, but then you must
 *   supply a `'path'` argument, too.
 *
 * - In `/app/config/bootstrap/libraries.php` add `Libraries::add('al13_helpers');`
 *
 * - And it is ready to use in view with autoloading by `$this->lists->*`
 *
 * **Description**
 *
 * To understand how this helper works there are two important concepts. Firstly, in a single
 * run of lithium, only one instance of any helper is used. There for we can temporarily "store"
 * information in it (as a property) between views, elements and layouts. In the most common
 * use of this helper, links are created in the view and in elements and then the layout renders
 * them. The reason why this works is the second important concept; the layout is rendered after
 * the view. Therefore we can add to the list of urls in when the layout is rendered, the menu
 * helper already know all that it is to render.
 *
 * Usage example 1: _Creating a list and rendering it_
 * {{{
 * // We have a list of links stored in the database and on this view we wish to list them out.
 * // $links being a collection of entities with at the properties 'url' and 'title':
 * foreach ($links as $link) {
 * 	$this->lists->add('link_list', array($link->title, $link->url));
 * }
 * echo $this->lists->generate('link_list');
 * }}}
 *
 * Usage example 2: _A multilevel list_
 * {{{
 * // Say we have an Article with hasMany Page, to render a list of links to both we could do :
 * foreach ($data as $article) {
 * 	$this->lists->add('articles', array($article->title, array(
 * 		'action' =>'view', $article->id
 * 	)));
 * 	foreach ($article->pages as $page) {
 * 		$this->lists->add(
 * 			array('articles', $article->className),
 * 			array($page->title, array(
 * 				'controller'=> 'pages', 'action' => 'view', $page->id
 * 			)
 * 		));
 * 	}
 * }
 * echo $this->lists->generate('articles');
 * }}}
 *
 * This will generate this:
 * {{{
 *  <ul class="menu_articles">
 *  <li><a href="/articles/view/1">Article 1</a></li>
 *  <ul class="menu_art_1_class_name">
 *  	<li><a href="/pages/view/1">Page 1</a></li>
 *  	<li><a href="/pages/view/2">Page 2</a></li>
 *  </ul>
 *  <li><a href="/articles/view/2">Article 2</a></li>
 *  <ul class="menu_art_2_class_name">
 *  	<li><a href="/pages/view/3">Page 3</a></li>
 *  	<li><a href="/pages/view/4">Page 4</a></li>
 *  </ul>
 *  </ul>
 * }}}
 *
 * **Customizations**
 *
 * If you wish to style the menus, take a look at the generated source code, each `<ul />` level
 * is given a unique class based on the target name. If you have need of more fine control,
 * you can use the `$options` paramter of the helpers methods to use image icons, class on
 * the `<a />` tags, id, class or style `<li />`, `<ul />` and `<div />`s. See each method for
 * specifics.
 *
 * @author Alexander Morland aka alkemann
 * @modified 27.june 2010
 * @version 0.3
 */
class Lists extends \lithium\template\Helper {

	private $_items = array('main' => array());

	/**
	 * Generate a list of links for pagination
	 *
	 * @param int $total
	 * @param int $limit
	 * @param int $page
	 * @return string Generated HTML
	 */
	public function pagination($total, $limit, $page) {
		if ($limit > $total) return;
		$ret = '<ul class="actions"><li>';

		if ($total <= $limit || $page == 1) {
			$ret .= 'First</li><li>Previous';
		} else {
			$ret .= $this->tag('link', array('title' => 'First',
				'url' => $this->_url(array('page' => 1, 'limit' => $limit))
			));
			$ret .= '</li><li>';
			$ret .= $this->tag('link', array('title' => 'Previous',
				'url' => $this->_url(array('page' => ($page-1), 'limit' => $limit))
			));
		}
		$ret .= '</li>';

		$p = 0; $count = $total;
		while ($count > 0) {
			$p++; $count -= $limit;
			$ret .= '<li>';
			if ($p == $page) {
				$ret .= '['.$p.']';
			} else {
				$ret .= $this->tag('link', array('title' => '['.$p.']',
					'url' => $this->_url(array('page' => $p, 'limit' => $limit))
				));
			}
			$ret .= '</li>';
		}
		$ret .= '<li>';
		if ($total <= $limit || $page == $p) {
			$ret .= 'Next</li><li>Last';
		} else {
			$ret .= $this->tag('link', array('title' => 'Next',
					'url' => $this->_url(array('page' => $page+1, 'limit' => $limit))
				));
			$ret .= '</li><li>';
			$ret .= $this->tag('link', array('title' => 'Last',
					'url' => $this->_url(array('page' => $p, 'limit' => $limit))
				));
		}
		$ret .= '</li></ul>';
		return $ret;
	}

	/**
	 * Create a drop-down select to manipulate the amount of items per pagination page
	 *
	 * @param array $list Optionally provide a value=>label array of options
	 * @return string
	 */
	public function limit_select($list = null) {
		$form = $this->_context->form;
		$ret = $form->create(null, array('id' => 'pagination-form', 'method' => 'GET'));
		$query = $this->_context->request()->query;
		foreach (array('sort', 'dir') as $field)
			if (isset($query[$field]))
				$ret .= $form->hidden($field, array('value' => $query[$field], 'id' => 'pagination-'.$field));
		if ($list == null)
			$list = array(
				'Page Count',
				5  => 'Five',
				10 => 'Ten',
				25 => '25',
				50 => 'Fifty'
			);
		$ret .= $form->select('limit', $list, array(
			'id' => 'pagination-limit',
			'onchange' => 'submit()'
		));
		$ret .= $form->end();
		return $ret;
	}

	/**
	 * Render a textbox that will let you filter by a field
	 *
	 * @param mixed $field
	 * @param array $options
	 * @return string
	 */
	public function filter_input($field, array $options = array()) {
		$form = $this->_context->form;
		$ret = $form->create(null, array('id' => 'filter-form', 'method' => 'GET'));

		$query = $this->_context->request()->query;
		foreach (array('sort', 'dir', 'limit') as $p)
			if (isset($query[$p]))
				$ret .= $form->hidden($p, array('value' => $query[$p], 'id' => 'filter-'.$p));

		if (is_array($field))
			$ret .= $form->select('field', $field, array('value' => isset($query['field']) ? $query['field'] : null ));
		else
			$ret .= $form->hidden('field', array('value' => $field));
		$ret .= $form->text('value', array('value' => isset($query['value']) ? $query['value'] : null ));
		$ret .= $form->submit('Filter');
		$ret .= $form->end();
		return $ret;
	}

	/**
	 * Create urls that remember parameters and querys
	 *
	 * @param array $query
	 * @param array $url
	 * @return array
	 */
	private function _url(array $query = array(), array $url = array()) {
		$request = $this->_context->request();
		$url = $url + $request->params;
		$url['?'] = $query + $request->query;
		return $url;
	}

	/**
	 * Create a html link for sorting by the field, used with pagination
	 *
	 * @param string $field
	 * @param string $title
	 * @return string
	 */
	public function sort_header($field, $title = null) {
		if (!$title) {
			$title = \lithium\util\Inflector::humanize($field);
		}
		$url = $this->_url();
		if (!isset($url['?']['dir'])) {
			$url['?']['dir'] = 'ASC';
		}
		if (isset($url['?']['sort']) && $url['?']['sort'] == $field) {
			$url['?']['dir'] = ($url['?']['dir'] == 'ASC') ? 'DESC' : 'ASC';
		}
		$url['?']['sort'] = $field;
		return $this->tag('link', array('title' => $title, 'url' => $url));
	}

	/**
	 * Adds a menu item to a target location
	 *
	 *
	 * @param mixed $target String or Array target notations
	 * @param array $link Array in same format as used by HtmlHelper::link()
	 * @param array $options
	 *  @options 'icon'  > $html->image() params
	 *  @options 'class' > <a class="?">
	 *  @options 'li'    > string:class || array('id','class','style')
	 *  @options 'div'	 > string:class || boolean:use || array('id','class','style')
	 *
	 * @return boolean successfully added
	 */
	function add($target = 'main', $link = array(), $options = array()) {

		if (!is_array($link) || !is_array($options) || !isset($link[0]) || !(is_array($link[0]) || is_string($link[0]))) {
			return false;
		}

		if (!isset($link[1])) {
			$link[1] = array();
		}

		if (!isset($link[2])) {
			$link[2] = array();
		}

		if (!isset($link[3])) {
			$link[3] = false;
		}

		if (!isset($link[4])) {
			$link[4] = true;
		}

		if (is_array($target)) {

			$depth = count($target);
			$menu = &$this->items;

			for ($i = 0; $i < $depth; $i++) {
				if (!empty($menu) && array_key_exists($target[$i], $menu)) {
					$menu = &$menu[$target[$i]];
				} else {
					$menu[$target[$i]] = array(true);
					$menu = &$menu[$target[$i]];
				}
			}

		} else {
			$menu = &$this->items[$target];
		}

		$menu[] = array($link, $options);

		return true;
	}

	/**
	 * Adds an element to a target item
	 *
	 * @param mixed $target String or Array target notations
	 * @param string $element Any string
	 * @param array $options
	 *  @options 'li'    > string:class || array('id','class','style')
	 *  @options 'div'	 > string:class || boolean:use || array('id','class','style')
	 *
	 * @return boolean successfully added
	 */
	function addElement($target = 'main', $element = false, $options = array()) {
		if ($element === false) {
			return false;
		}

		if (is_array($target)) {

			$depth = count($target);
			$menu = &$this->items;

			for ($i = 0; $i < $depth; $i++) {
				if (!empty($menu) && array_key_exists($target[$i], $menu)) {
					$menu = &$menu[$target[$i]];
				} else {
					$menu[$target[$i]] = array(true);
					$menu = &$menu[$target[$i]];
				}
			}

		} else {
			$menu = &$this->items[$target];
		}

		$menu[] = array(1 => $options, 2 => $element);

		return true;
	}

	/**
	 * Renders and returns the generated html for the targeted item and its element and children
	 *
	 * @param mixed $source String or Array target notations
	 * @param array $options
	 *  @options 'class' > <ul class="?"><li><ul>..</li></ul>
	 *  @options 'id' 	 > <ul id="?"><li><ul>..</li></ul>
	 *  @options 'ul'    > string:class || array('class','style')
	 *  @options 'div'	 > string:class || boolean:use || array('id','class','style')
	 *  @options 'active'> array('tag' => string(span,strong,etc), 'attributes' => array(htmlAttributes), 'strict' => boolean(true|false)))
	 *
	 * @example echo $this->lists->generate('context', array('active' => array('tag' => 'link','attributes' => array('style' => 'color:red;','id'=>'current'))));
	 * @return mixed string generated html or false if target doesnt exist
	 */
	function generate($source = 'main', $options = array()) {

		$out = '';
		$list = '';

		if (isset($options['ul']))
			$ulAttributes = $options['ul'];
		else
			$ulAttributes = array();

		// DOM class attribute for outer UL
		if (isset($options['class'])) {
			$ulAttributes['class'] = $options['class'];
		} else {
			if (is_array($source)) {
				$ulAttributes['class'] = 'menu_' . $source[count($source) - 1];
			} else {
				$ulAttributes['class'] = 'menu_' . $source;
			}
		}

		// DOM element id for outer UL
		if (isset($options['id'])) {
			$ulAttributes['id'] = $options['id'];
		}

		$menu = $this->findSource($source, $options);
		if ($menu === false) {
			return false;
		}

		if (isset($options['reverse']) && $options['reverse'] == true) {
			unset($options['reverse']);
			$menu = array_reverse($menu);
		}

		$requestObj = $this->_context->request();
		if (isset($options['active']['strict']) && !$options['active']['strict']) {
			$requestParams = $requestObj->params;
			$here = trim(Router::match(array(
				'controller' => $requestParams['controller'],
				'action' => $requestParams['action']
			), $requestObj), "/");
		} else {
			$here = $requestObj->url;
		}

		$base = Router::match('/', $requestObj);
		if ($base == '/') {
			$baseOffset = 1;
		} else {
			$baseOffset = strlen($base);
		}
		// Generate menu items
		foreach ($menu as $key => $item) {
			$liAttributes = array();
			$aAttributes = array();

			if (isset($item[1]['li'])) {
				$liAttributes = $item[1]['li'];
			}

			if (isset($item[0]) && $item[0] === true) {
				$menusource = $source;
				if (!is_array($menusource)) {
					$menusource = array($menusource);
				}
				$menusource[] = $key;
				// Don't set DOM element id on sub menus */
				if (isset($options['id'])) {
					unset($options['id']);
				}
				$listitem = $this->generate($menusource, $options);
				if (empty($listitem)) {
					continue;
				}
			} elseif (isset($item[0])) {
				if (!isset($item[0][2]['title'])) {
					$item[0][2]['title'] = $item[0][0];
				}
				$routeUrl = Router::match($item[0][1], $requestObj);
				if ($baseOffset) $routeUrl = substr($routeUrl, $baseOffset);
				$active = ($here == $routeUrl) || ($here == '/' && empty($routeUrl));
				if ( isset($options['active']) && $options['active'] && $active) {
					if (isset($options['active']['li']) && is_array($options['active']['li'])) {
						$liAttributes += $options['active']['li'];
					}
					if (is_array($options['active'])) {
						$tagOptions = array();
						foreach ($options['active'] as $a => $v) {
							if ($a == 'tag' || $a == 'strict' || $a == 'options') continue;
							if ($a == 'title') $tagOptions[$v] = $item[0][1];
							elseif ($a == 'url') $tagOptions[$v] = $item[0][0];
						}
						if (empty($tagOptions)) $tagOptions['content'] = $item[0][0];
						$tag = isset($options['active']['tag'])?$options['active']['tag']:'span';
					} else {
						$tag = 'span';
						$tagOptions['content'] = $item[0][0];
					}
					$tagOptions['options'] = isset($options['active']['options'])? $options['active']['options']: array();
					$listitem = $this->tag($tag, $tagOptions);
				} else {
					$listitem = $this->tag('link', array(
						'title' => $item[0][0],
						'url' => $item[0][1],
						'options' => $item[0][2]
					));
				}
			} elseif (isset($item[2])) {
				$listitem = $item[2];
			} else {
				continue;
			}

			if (isset($item[1]['div']) && $item[1]['div'] !== false) {
				$divOptions = array();
				if (is_array($item[1]['div'])) {
					$divOptions = $item[1]['div'];
				}
				$listitem = $this->tag('block',
						array('content' => $listitem,'options' => $divOptions));
			}
			if (substr($listitem,0,3) == '<ul') {
				$list .= $listitem;
			} else {
				$list .= $this->tag('list-item',
					array('content' => $listitem,'options' => $liAttributes));
			}
		}

		// Generate menu
		$out .= $this->tag('list', array('content' => $list, 'options' => $ulAttributes));

		// Add optional outer div
		if (isset($options['div']) && $options['div'] !== false) {
			$divOptions = array();
			if (is_array($options['div'])) {
				$divOptions = $options['div'];
			}
			$out = $this->tag('block', array('content' => $out, 'options' => $divOptions));
		}
		return $out;
	}

	public function count($source = 'main') {
		$list = $this->findSource($source);
		if (!$list) return 0;
		return count($list);
	}

	private function findSource($source, $options = array()) {

		$list = array();
		// Find source menu
		if (is_array($source)) {

			$depth = count($source);
			$list = &$this->items;

			for ($i = 0; $i < $depth; $i++) {
				if (!empty($list) && array_key_exists($source[$i], $list)) {
					$list = &$list[$source[$i]];
				} else {
					if (!isset($options['force']) || (isset($options['force']) && !$options['force']))
						return false;
				}
			}

		} else {
			if (!isset($this->items[$source])) {
				if (!isset($options['force']) || (isset($options['force']) && !$options['force']))
					return false;
			} else {
				$list = &$this->items[$source];
			}
		}
		return $list;
	}

	protected $_strings = array(
		'block'            => '<div{:options}>{:content}</div>',
		'block-end'        => '</div>',
		'block-start'      => '<div{:options}>',
		'charset'     	   => '<meta http-equiv="Content-Type" content="{:type}; charset={:charset}" />',
		'image'            => '<img src="{:path}"{:options} />',
		'js-block'         => '<script type="text/javascript"{:options}>{:content}</script>',
		'js-end'           => '</script>',
		'js-start'         => '<script type="text/javascript"{:options}>',
		'link'             => '<a href="{:url}"{:options}>{:title}</a>',
		'list'             => '<ul{:options}>{:content}</ul>',
		'list-item'        => '<li{:options}>{:content}</li>',
		'meta'             => '<meta{:options}/>',
		'meta-link'        => '<link href="{:url}"{:options} />',
		'para'             => '<p{:options}>{:content}</p>',
		'para-start'       => '<p{:options}>',
		'script'           => '<script type="text/javascript" src="{:path}"{:options}></script>',
		'style'            => '<style type="text/css"{:options}>{:content}</style>',
		'style-import'     => '<style type="text/css"{:options}>@import url({:url});</style>',
		'style-link'       => '<link rel="{:type}" type="text/css" href="{:path}"{:options} />',
		'table-header'     => '<th{:options}>{:content}</th>',
		'table-header-row' => '<tr{:options}>{:content}</tr>',
		'table-cell'       => '<td{:options}>{:content}</td>',
		'table-row'        => '<tr{:options}>{:content}</tr>',
		'strong'           => '<strong{:options}>{:content}</strong>',
		'span'             => '<span{:options}>{:content}</span>',
		'tag'              => '<{:name}{:options}>{:content}</{:name}>',
		'tag-end'          => '</{:name}>',
		'tag-start'        => '<{:name}{:options}>'
	);

	public function tag($tag, $options = array()) {
		if ($tag == 'link' && !isset($options['options'])) $options['options'] = array();
		return $this->_render(__METHOD__, $tag, $options, array('escape' => false));
	}
}
