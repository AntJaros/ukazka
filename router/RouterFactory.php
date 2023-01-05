<?php

namespace App;

use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;


class RouterFactory
{
	use Nette\StaticClass;

	/**
	 * @return Nette\Application\IRouter
	 */
	public static function createRouter()
	{
		$router = new RouteList;
		$router[] = new Route('sitemap.xml', 'Front:Homepage:sitemap');
		$router[] = new Route('', 'Front:Homepage:default');
		$router[] = new Route('login', 'Front:Sign:in');
		$router[] = new Route('fblogin', 'Front:Sign:fb');
		$router[] = new Route('register', 'Front:Sign:up');
		$router[] = new Route('logout', 'Front:Sign:out');
		$router[] = new Route('forgot', 'Front:Sign:forgot');
		$router[] = new Route('reset', 'Front:Sign:reset');
		$router[] = new Route('cookies', 'Front:Doc:cookies');
		$router[] = new Route('privacy', 'Front:Doc:privacy');
		$router[] = new Route('rights', 'Front:Doc:rights');
		$router[] = new Route('nick?<id>', 'Front:Sign:nick');
		$router[] = new Route('confirm?<code>', 'Front:Sign:confirm');
		$router[] = new Route('youtuber/<slug>', 'Front:Youtuber:default');
		$router[] = new Route('user/<id>[-<nick>]', 'Front:User:default');
		$router[] = new Route('novinky', 'Front:Article:list');
		$router[] = new Route('novinka/<slug>', 'Front:Article:detail');
		$router[] = new Route('kategorie/<slug>', 'Front:Category:default');
		$router[] = new Route('admin386/', 'Admin:Homepage:default');
		$router[] = new Route('admin386/login', 'Admin:Sign:in');
		$router[] = new Route('admin386/<presenter>/<action>', [
			'module' => 'Admin'
		]);
		$router[] = new Route('<presenter>/<action>', 'Front:Homepage:default');

		return $router;
	}
}



