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
	 * Comparison by PHP function version_compare();
	 * @see http://php.net/manual/en/function.version-compare.php
	 */
	const VERSION = '5.0.0-alpha';

	/**
	 * Reference to main application instance.
	 * @var \MvcCore\Application|\MvcCore\IApplication
	 */
	protected $app = NULL;

	/**
	 * Full class name for configured core tools class.
	 * @var string|NULL
	 */
	protected $toolClass = NULL;

	/**
	 * Reference to current application request instance.
	 * @var \MvcCore\Request|\MvcCore\IRequest
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
	 * @var \MvcCore\Router|\MvcCore\IRouter
	 */
	protected $router = NULL;

	/**
	 * Reference to all initialized application routes in router.
	 * @var \MvcCore\Route[]|\MvcCore\IRoute[]
	 */
	protected $routes = [];

	/**
	 * Reference to route matched by current request.
	 * @var \MvcCore\Route|\MvcCore\IRoute
	 */
	protected $currentRoute = NULL;

	/**
	 * Default language for possible multi-language router version.
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
	 * - complete basic \MvcCore core objects to complete other view data
	 * - complete panel title
	 * - complete routes table items
	 * - set result data into static field
	 * @return object
	 */
	public function getViewData () {
		if ($this->view !== NULL) return $this->view;
		$this->view = new \stdClass;
		try {
			// complete basic \MvcCore core objects to complete other view data
			$this->initMainApplicationProperties();
			// those cases are only when request is redirected very soon
			if ($this->router === NULL) return $this->view;
			// complete panel title
			$this->initViewPanelTitle();
			// complete routes table items
			$this->initViewPanelTableData();
			// complete requested URL data under routes table
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
		$router = & $this->router;
		$ctrlParamName = $router::URL_PARAM_CONTROLLER;
		$actionParamName = $router::URL_PARAM_ACTION;
		$getParamsKeys = array_unique(array_merge(
			[$ctrlParamName => NULL, $actionParamName => NULL],
			$this->currentRoute ? $this->currentRoute->GetMatchedParams() : [],
			array_keys($_GET)
		));
		$this->requestParams = & $this->request->GetParams(['#[\<\>\'"]#' => ''], array_keys($getParamsKeys));
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
		/** @var $route \MvcCore\IRoute */
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
	 * @param \MvcCore\IRoute $route
	 * @param bool $matched
	 * @return \stdClass
	 */
	protected function initViewPanelTableRow (\MvcCore\IRoute & $route, $matched) {
		$route->InitAll();
		$row = new \stdClass;

		// first column
		$row->matched = $matched;

		// second column
		$row->method = $route->GetMethod();
		$row->method = $row->method === NULL ? '*' : $row->method;

		// third column
		$row->className = htmlSpecialChars('\\'.get_class($route), ENT_QUOTES, 'UTF-8');
		$routeMatch = $this->getRouteLocalizedRecord($route, 'GetMatch');
		$routeMatch = rtrim($routeMatch, 'imsxeADSUXJu'); // remove all modifiers
		$routeReverse = $this->getRouteLocalizedRecord($route, 'GetReverse');
		$routeDefaults = $this->getRouteLocalizedRecord($route, 'GetDefaults');
		$row->match = $this->completeFormatedPatternCharGroups($routeMatch, ['(', ')']);
		unset($routeMatch);
		if ($routeReverse !== NULL) {
			$row->reverse = $this->completeFormatedPatternCharGroups($routeReverse, ['<', '>']);
		} else {
			$row->reverse = NULL;
		}
		unset($routeReverse);

		// fourth column
		$row->routeName = $route->GetName();
		$row->ctrlActionName = $route->GetControllerAction();
		if ($row->ctrlActionName !== ':') {
			$row->ctrlActionLink = $this->completeCtrlActionLink($route->GetController(), $route->GetAction());
		} else {
			$row->ctrlActionName = NULL;
			$row->ctrlActionLink = NULL;
		}
		$routeReverseParams = $route->GetReverseParams() ?: []; // route could NULL reverse params when redirect route defined
		$paramsKeys = array_unique(array_merge($routeReverseParams, array_keys($routeDefaults)));
		unset($routeReverseParams);
		$row->defaults = $this->completeParams($route, $paramsKeys, TRUE);
		unset($paramsKeys);

		// fifth column (only for matched route)
		$row->params = [];
		if ($matched) {
			$paramsAndReqestParams = array_merge($routeDefaults, $this->requestParams);
			unset($routeDefaults);
			$row->params = $this->completeParams($route, array_keys($paramsAndReqestParams), FALSE);
			unset($paramsAndReqestParams, $route);
		}

		return $row;
	}

	/**
	 * Complete fourth column (and fifth if matched) params collection string for template.
	 * @param \MvcCore\IRoute $route
	 * @param array $paramsNames Array with param keys to render.
	 * @param bool  $useDefaults If `TRUE`, render params from route defaults, if `FALSE`, render params from request params.
	 * @return array
	 */
	protected function completeParams (\MvcCore\IRoute & $route, $paramsNames = [], $useDefaults = TRUE) {
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
		$router = & $this->router;
		$ctrlParamName = $router::URL_PARAM_CONTROLLER;
		$actionParamName = $router::URL_PARAM_ACTION;
		foreach ($paramsNames as $key => $paramName) {
			if ($paramName == $ctrlParamName || $paramName == $actionParamName) {
				if (!in_array($paramName, $routeDefaultsKeys, TRUE) && !isset($_GET[$paramName])) continue;
			}
			$paramValue = isset($paramValues[$paramName])
				? $paramValues[$paramName]
				: NULL;
			if ($key === 0 && $paramName === 0 && $paramValue === NULL) continue; // weird fix
			$paramNameEncoded = htmlSpecialChars($paramName, ENT_IGNORE, 'UTF-8');
			if ($paramValue === NULL) {
				$paramValueRendered = '<span class="tracy-dump-null">NULL</span><br />';
			} else if (is_string($paramValue)) {
				$paramValueRendered = '<span class="tracy-dump-string">"'
					. htmlSpecialChars($paramValue, ENT_IGNORE, 'UTF-8')
					. '"</span><br />';
			} else {
				$paramValueRendered = \Tracy\Dumper::toHtml($paramValue, [
					\Tracy\Dumper::COLLAPSE	=> TRUE,
					\Tracy\Dumper::LIVE		=> TRUE
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
	 * info about substrings between them and their opening and closing
	 * positions to complete task with character group colouring in
	 * local method $this->completeFormatedPatternOrReverseCharGroups().
	 * @param string $str	string to search brackets in
	 * @param string $begin	opening bracket char
	 * @param string $end	closing bracket char
	 * @return array[]
	 */
	protected function completeMatchingBracketsPositions ($str, $begin, $end) {
		$result = [];
		$i = 0;
		$l = mb_strlen($str);
		$matches = [];
		while ($i < $l) {
			$beginPos = mb_strpos($str, $begin, $i);
			$endPos = mb_strpos($str, $end, $i);
			$beginContained = $beginPos !== FALSE;
			$endContained = $endPos !== FALSE;
			if ($beginContained && $endContained) {
				if ($beginPos < $endPos) {
					$matches[] = [$begin, $beginPos];
					$i = $beginPos + 1;
				} else {
					$matches[] = [$end, $endPos];
					$i = $endPos + 1;
				}
			} else if ($beginContained) {
				$matches[] = [$begin, $beginPos];
				$i = $beginPos + 1;
			} else if ($endContained) {
				$matches[] = [$end, $endPos];
				$i = $endPos + 1;
			} else {
				break;
			}
		}
		if ($matches) {
			$level = 0;
			$groupBegin = -1;
			$paramLevel = 0;
			foreach ($matches as $item) {
				list($itemChar, $itemPos) = $item;
				$backSlashesCnt = 0;
				$backSlashPos = $itemPos - 1;
				while ($backSlashPos > -1) {
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
						if ($begin == '(') {
							$itemCharNext = mb_substr($str, $itemPos + 1, 1);
							if ($itemCharNext !== '?') {
								$level += 1;
								continue;
							}
						}
						$paramLevel = $level;
						$groupBegin = $itemPos;
						$level += 1;
					} else {
						$level -= 1;
						if ($level === $paramLevel && $groupBegin > -1) {
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
		unset(
			$str, $begin, $end,
			$beginPos, $endPos, 
			$beginContained, $endContained, 
			$matches, $item, $i, $l,
			$level, $groupBegin, $paramLevel,
			$itemPos, $itemCharNext, $itemChar,
			$backSlashesCnt, $backSlashPos, $prevChar
		);
		return $result;
	}

	/**
	 * Try to complete editor link by controller class name and it's action
	 * by PHP reflection object if controller class exist and it's possible by
	 * loaded controller class to create reflection object to complete link properties.
	 * Result is returned as array containing:
	 *  0 - editor link URL
	 *  1 - link text
	 * @param string $ctrlName
	 * @param string $actionName
	 * @return array
	 */
	protected function completeCtrlActionLink ($ctrlName = '', $actionName = '') {
		$fullControllerClassName = '';
		static $controllersDir = NULL;
		if ($controllersDir === NULL) {
			$controllersDir = '\\' . implode('\\', [$this->app->GetAppDir(), $this->app->GetControllersDir()]) . '\\';
		}
		if (substr($ctrlName, 0, 2) == '//') {
			$fullControllerClassName = $ctrlName;
			$fullClassToSearch = substr($ctrlName, 2);
		} else {
			$fullControllerClassName = $controllersDir . $ctrlName;
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
	 * @param \MvcCore\IRoute $route
	 * @param string $getter
	 * @return string|array
	 */
	protected function getRouteLocalizedRecord (\MvcCore\IRoute & $route, $getter) {
		$result = $route->$getter($this->requestLang);
		if ($result === NULL && $this->defaultLang !== NULL) 
			$result = $route->$getter($this->defaultLang);
		return $result;
	}

	/**
	 * Complete data about requested URL under routes table.
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
			\Tracy\Dumper::LIVE		=> TRUE,
			//\Tracy\Dumper::DEPTH	=> 5,
		]);
	}
}
