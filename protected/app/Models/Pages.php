<?php

class Pages {
	
	public function index() {
		return "Index";
	}

	public function hello($name) {
		return ", {$name}!";
	}

}