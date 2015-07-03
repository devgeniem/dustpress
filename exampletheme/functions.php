<?php

function register_custom_menu() {
	register_nav_menu( "primary", "Primary menu" );
}

add_action("after_setup_theme", "register_custom_menu");