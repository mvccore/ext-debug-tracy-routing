<?php

/**
 * MvcCore
 *
 * This source file is subject to the BSD 3 License
 * For the full copyright and license information, please view
 * the LICENSE.md file that are distributed with this source code.
 *
 * @copyright	Copyright (c) 2016 Tom Flídr (https://github.com/mvccore/mvccore)
 * @license		https://mvccore.github.io/docs/mvccore/4.0.0/LICENCE.md
 */

namespace MvcCore\Ext\Debugs\Tracys;

/**
 * Responsibility - render used routes by application router and mark and render matched route with request params.
 */
class RoutingPanel implements \Tracy\IBarPanel
{
	/**
	 * MvcCore Extension - Debug - Tracy Panel - Routing - version:
	 * Comparation by PHP function version_compare();
	 * @see http://php.net/manual/en/function.version-compare.php
	 */
	const VERSION = '5.0.0-alpha';

	/**
	 * Reference to main application instance.
	 * @var \MvcCore\Application|\MvcCore\Interfaces\IApplication
	 */
	protected $app = NULL;

	/**
	 * Full class name for configured core tools class.
	 * @var string|NULL
	 */
	protected $toolClass = NULL;

	/**
	 * Reference to current application request instance.
	 * @var \MvcCore\Request|\MvcCore\Interfaces\IRequest
	 */
	protected $request = NULL;

	/**
	 * Currently requested language.
	 * @var string|NULL
	 */
	protected $requestLang = NULL;

	/**
	 * Currently requested params with escaped `<` and `>`.
	 * @var array
	 */
	protected $requestParams = [];

	/**
	 * Reference to current application router instance.
	 * @var \MvcCore\Router|\MvcCore\Interfaces\IRouter
	 */
	protected $router = NULL;

	/**
	 * Reference to all initialized application routes in router.
	 * @var \MvcCore\Route[]|\MvcCore\Interfaces\IRoute[]
	 */
	protected $routes = [];

	/**
	 * Reference to route matched by current request.
	 * @var \MvcCore\Route|\MvcCore\Interfaces\IRoute
	 */
	protected $currentRoute = NULL;

	/**
	 * Default language for possible multilanguage router version.
	 * @var string|NULL
	 */
	protected $defaultLang = NULL;

	/**
	 * Prepared view data, only once,
	 * to render debug tab and debug panel content.
	 * @var \stdClass|NULL
	 */
	protected $view = NULL;

	/**
	 * Debug code for this panel, printed at panel bottom.
	 * @var string
	 */
	private $_debugCode = '';

	/**
	 * Return unique panel id.
	 * @return string
	 */
	public function getId() {
		return 'routing-panel';
	}
	/**
	 * Render tab (panel header).
	 * Set up view data if necessary.
	 * @return string
	 */
	public function getTab() {
		ob_start();
		$view = $this->getViewData();
		if ($view) include(__DIR__ . '/routing.tab.phtml');
		return ob_get_clean();
	}
	/**
	 * Render panel (panel content).
	 * Set up view data if necessary.
	 * @return string
	 */
	public function getPanel() {
		ob_start();
		$view = $this->getViewData();
		if ($view) include(__DIR__ . '/routing.panel.phtml');
		return ob_get_clean();
	}
	/**
	 * Set up view data, if data are completed,
	 * return them directly.
	 * - complete basic \MvcCore core objects to complere other view data
	 * - complete panel title
	 * - complete routes table items
	 * - set result data into static field
	 * @return object
	 */
	public function getViewData () {
		if ($this->view !== NULL) return $this->view;
		$this->view = new \stdClass;
		try {
			// complete basic \MvcCore core objects to complere other view data
			$this->initMainApplicationProperties();
			// those cases are only when request is redirected very soon
			if ($this->router === NULL) return $this->view;
			// complete panel title
			$this->initViewPanelTitle();
			// complete routes table items
			$this->initViewPanelTableData();
			// complete requested url data under routes table
			$this->initViewPanelRequestedUrlData();
		} catch (\Exception $e) {
			$this->_debug($e);
			$this->_debug($e->getTrace());
		}
		// debug code
		$this->view->_debugCode = $this->_debugCode;
		return $this->view;
	}

	/**
	 * Initialize main application properties into current `$this`
	 * context: app, request, router, routes and current route.
	 * @return void
	 */
	protected function initMainApplicationProperties () {
		$this->app = & \MvcCore\Application::GetInstance();
		$this->router = & $this->app->GetRouter();
		$this->routes = & $this->router->GetRoutes();
		$this->currentRoute = $this->router->GetCurrentRoute();
		$this->request = & $this->app->GetRequest();
		$this->requestLang = $this->request->GetLang();
		$getParamsKeys = array_unique(array_merge(
			['controller', 'action'],
			$this->currentRoute ? $this->currentRoute->GetReverseParams() : [],
			array_keys($_GET)
		));
		$this->requestParams = & $this->request->GetParams(['#[\<\>]#', ''], $getParamsKeys);
		if (method_exists($this->router, 'GetDefaultLang'))
			$this->defaultLang = $this->router->GetDefaultLang();
	}

	/**
	 * Initialize panel title. Set into panel title current route name
	 * with optional `Controller:Action` combination if route name is different from it
	 * and set `No route match` if no route matched by default.
	 * @return void
	 */
	protected function initViewPanelTitle () {
		$panelTitle = 'No route match';
		if ($this->currentRoute !== NULL)
			$panelTitle = $this->currentRoute->GetName();
		$this->view->panelTitle = htmlSpecialChars($panelTitle, ENT_NOQUOTES, 'UTF-8');
	}

	/**
	 * Complete routes table data by router routes.
	 * @return void
	 */
	protected function initViewPanelTableData () {
		$items = [];
		$currentRouteName = $this->currentRoute ? $this->currentRoute->GetName() : NULL;
		/** @var $route \MvcCore\Interfaces\IRoute */
		foreach ($this->routes as & $route) {
			$matched = FALSE;
			if ($currentRouteName !== NULL && $route->GetName() === $currentRouteName) {
				$matched = TRUE;
			}
			$items[] = $this->initViewPanelTableRow($route, $matched);
		}
		$this->view->items = $items;
	}

	/**
	 * Complete single route table row view data.
	 * @param \MvcCore\Interfaces\IRoute $route
	 * @param bool $matched
	 * @return \stdClass
	 */
	protected function initViewPanelTableRow (\MvcCore\Interfaces\IRoute & $route, $matched) {
		$route->InitAll();
		$row = new \stdClass;

		// first column
		$row->matched = $matched;

		// second column
		$row->method = $route->GetMethod();
		$row->method = $row->method === NULL ? '*' : $row->method;

		// third column
		$row->className = htmlSpecialChars('\\'.get_class($route), ENT_QUOTES, 'UTF-8');
		$routePattern = $this->getRouteLocalizedRecord($route, 'GetMatch');
		$routeReverse = $this->getRouteLocalizedRecord($route, 'GetReverse');
		$row->match = $this->completeFormatedPatternCharGroups($routePattern, ['(', ')']);
		$row->reverse = $this->completeFormatedPatternCharGroups($routeReverse, ['<', '>']);

		// fourth column
		$row->routeName = $route->GetName();
		$row->ctrlActionName = $route->GetControllerAction();
		$row->ctrlActionLink = $this->completeCtrlActionLink($route->GetController(), $route->GetAction());
		$params = array_merge($route->GetReverseParams(), $route->GetDefaults());
		$row->defaults = $this->completeParams($route, array_keys($params), TRUE);

		// fifth column (only for matched route)
		$row->params = [];
		if ($matched) {
			$paramsAndReqestParams = array_merge($params, $this->requestParams);
			$row->params = $this->completeParams($route, array_keys($paramsAndReqestParams), FALSE);
		}

		return $row;
	}

	/**
	 * Complete fourth column (and fifth if matched) params collection string for template.
	 * @param \MvcCore\Interfaces\IRoute $route
	 * @param array $paramsNames Array with param keys to render.
	 * @param bool  $useDefaults If `TRUE`, render params from route defaults, if `FALSE`, render params from request params.
	 * @return array
	 */
	protected function completeParams (\MvcCore\Interfaces\IRoute & $route, $paramsNames = [], $useDefaults = TRUE) {
		$result = [];
		if ($this->defaultLang !== NULL) {
			$result['lang'] = '<span class="tracy-dump-string">"' . $this->requestLang . '"</span><br />';
		}
		if (!$paramsNames) return $result;
		$routeDefaults = $this->getRouteLocalizedRecord($route, 'GetDefaults');
		$routeDefaultsKeys = array_keys($routeDefaults);
		if ($useDefaults) {
			$paramValues = $routeDefaults;
		} else {
			$paramValues = $this->requestParams;
		}
		foreach ($paramsNames as $key => $paramName) {
			if ($paramName == 'controller' || $paramName == 'action') {
				if (!in_array($paramName, $routeDefaultsKeys) && !isset($_GET[$paramName])) continue;
			}
			$paramValue = isset($paramValues[$paramName])
				? $paramValues[$paramName]
				: NULL;
			if ($key === 0 && $paramName === 0 && $paramValue === NULL) continue; // weird bugxif
			$paramNameEncoded = htmlSpecialChars($paramName, ENT_IGNORE, 'UTF-8');
			if (is_string($paramValue)) {
				$paramValueRendered = '<span class="tracy-dump-string">"'
					. htmlSpecialChars($paramValue, ENT_IGNORE, 'UTF-8')
					. '"</span><br />';
			} else {
				$paramValueRendered = \Tracy\Dumper::toHtml($paramValue, [
					\Tracy\Dumper::COLLAPSE => TRUE,
					\Tracy\Dumper::LIVE => TRUE
				]);
			}
			$result[$paramNameEncoded] = $paramValueRendered;
		}
		return $result;
	}

	/**
	 * Add into route regular expression pattern or reverse ($route->GetPattern()
	 * or $route->GetReverse()) around all detected character groups special
	 * html span elements to color them in template.
	 * @param string   $str	  route pattern string or reverse string
	 * @param string[] $brackets array with specified opening bracket and closing bracket type
	 * @return string
	 */
	protected function completeFormatedPatternCharGroups ($str, $brackets) {
		$matches = $this->completeMatchingBracketsPositions($str, $brackets[0], $brackets[1]);
		if ($matches) {
			$pos = 0;
			$result = '';
			foreach ($matches as $key => & $match) {
				list($subStr, $begin, $end) = $match;
				$result .= mb_substr($str, $pos, $begin - $pos);
				$result .= '<span class="c'.($key % 6).'">';
				$result .= htmlSpecialChars($subStr, ENT_NOQUOTES, 'UTF-8');
				$result .= '</span>';
				$pos = $end + 1;
			}
			$result .= mb_substr($str, $pos);
		} else {
			$result = $str;
		}
		return $result;
	}

	/**
	 * Complete collection with first level matching brackets,
	 * info about substrings between them and theur opening and closing
	 * positions to complete task with character group coloring in
	 * local method $this->completeFormatedPatternOrReverseCharGroups().
	 * @param string $str	string to search brackets in
	 * @param string $begin	opening bracket char
	 * @param string $end	closing bracket char
	 * @return array[]
	 */
	protected function completeMatchingBracketsPositions ($str, $begin, $end) {
		$result = [];
		preg_match_all('#([\\'.$begin.'\\'.$end.'])#', $str, $matches, PREG_OFFSET_CAPTURE);
		if ($matches[0]) {
			$matches = $matches[0];
			$level = 0;
			$groupBegin = -1;
			foreach ($matches as $item) {
				list($itemChar, $itemPos) = $item;
				$backSlashesCnt = 0;
				$backSlashPos = $itemPos - 1;
				while ($backSlashPos > -1 && true) {
					$prevChar = mb_substr($str, $backSlashPos, 1);
					if ($prevChar == '\\') {
						$backSlashesCnt += 1;
						$backSlashPos -= 1;
					} else {
						break;
					}
				}
				if (
					$backSlashesCnt === 0 || (
					($backSlashesCnt > 0 && $backSlashesCnt % 2 === 0)
				)) {
					if ($itemChar == $begin) {
						if ($level === 0) {
							$groupBegin = $itemPos;
						}
						$level += 1;
					} else {
						$level -= 1;
						if ($level === 0) {
							$result[] = [
								mb_substr($str, $groupBegin, $itemPos - $groupBegin + 1),
								$groupBegin,
								$itemPos
							];
						}
					}
				}
			}
		}
		// remove trailing slash match group
		$resultCount = count($result);
		if ($resultCount > 0) {
			$lastIndex = count($result) - 1;
			if ($result[$lastIndex][0] == '(?=/$|$)')
				unset($result[$lastIndex]);
		}
		return $result;
	}

	/**
	 * Try to complete editor link by controller class name and it's action
	 * by PHP reflection object if controller class exist and it's possible by
	 * loaded controller class to create reflection object to complete link properties.
	 * Result is returned as array containing:
	 *  0 - editor link url
	 *  1 - link text
	 * @param string $ctrlName
	 * @param string $actionName
	 * @return array
	 */
	protected function completeCtrlActionLink ($ctrlName = '', $actionName = '') {
		$fullControllerClassName = '';
		if (substr($ctrlName, 0, 1) == '\\') {
			$fullControllerClassName = $ctrlName;
			$fullClassToSearch = substr($ctrlName, 1);
		} else {
			$fullControllerClassName = '\\App\\Controllers\\' . $ctrlName;
			$fullClassToSearch = $fullControllerClassName;
		}
		$result = ['', $fullControllerClassName . ':' . $actionName . 'Action'];
		try {
			$ctrlReflection = new \ReflectionClass($fullClassToSearch);
			if ($ctrlReflection instanceof \ReflectionClass) {
				$file = $ctrlReflection->getFileName();
				$actionReflection = $ctrlReflection->getMethod($actionName . 'Action');
				if ($actionReflection instanceof \ReflectionMethod) {
					$line = $actionReflection->getStartLine();
					$result = [
						\Tracy\Helpers::editorUri($file, $line),
						$fullControllerClassName . ':' . $actionName . 'Action'
					];
				}
			}
		} catch (\Exception $e) {
		}
		return $result;
	}

	/**
	 * Get route non-localized or localized record - 'Pattern' and 'Reverse'
	 * @param \MvcCore\Interfaces\IRoute $route
	 * @param string $getter
	 * @return string|array
	 */
	protected function getRouteLocalizedRecord (\MvcCore\Interfaces\IRoute & $route, $getter) {
		$result = $route->$getter($this->requestLang);
		if ($result === NULL && $this->defaultLang !== NULL) {
			$result = $route->$getter($this->defaultLang);
		}
		return $result;
	}

	/**
	 * Complete data about requested url under routes table.
	 * @return void
	 */
	protected function initViewPanelRequestedUrlData () {
		$req = & $this->request;
		$this->view->requestedUrl = (object) [
			'method'	=> htmlSpecialChars($req->GetMethod(), ENT_IGNORE, 'UTF-8'),
			'baseUrl'	=> htmlSpecialChars($req->GetBaseUrl(), ENT_IGNORE, 'UTF-8'),
			'path'		=> htmlSpecialChars($req->GetRequestPath(), ENT_IGNORE, 'UTF-8'),
		];
	}

	/**
	 * Print any variable in panel body under routes table.
	 * @param mixed $var
	 * @return void
	 */
	private function _debug ($var) {
		$this->_debugCode .= \Tracy\Dumper::toHtml($var, [
			\Tracy\Dumper::LIVE => TRUE
		]);
	}
}
