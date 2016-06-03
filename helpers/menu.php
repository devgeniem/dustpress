<?php
namespace DustPress;

class Menu extends Helper
{
    public function output() {
		if ( ! isset( $this->params->menu_name ) && ! isset( $this->params->menu_id ) ) {
			return $this->chunk->write("DustPress menu helper error: No menu specified.");
		}
		else if ( isset( $this->params->menu_id ) ) {
			$menu_id = $this->params->menu_id;
			$id_given = true;
		}
		else {
			$menu_name = $this->params->menu_name;
		}

		if ( isset( $this->params->parent ) ) {
			$parent = $this->params->parent;
		}
		else {
			$parent = 0;
		}

		if ( isset( $this->params->override ) ) {
			$override = $this->params->override;
		}
		else {
			$override = null;
		}

		if ( isset( $this->params->ul_classes ) ) {
			$ul_classes = $this->params->ul_classes;
		}
		else {
			$ul_classes = "";
		}

		if ( isset( $this->params->ul_id ) ) {
			$ul_id = $this->params->ul_id;
		}
		else {
			$ul_id = "";
		}

		if ( isset( $this->params->show_submenu ) ) {
			$show_submenu = $this->params->show_submenu;
		}
		else {
			$show_submenu = true;
		}

		$menu = new \stdClass();

		if ( $menu_name ) {
			$menu->items = \DustPressHelper::get_menu_as_items( $menu_name, $parent, $override );
		}
		else {
			$menu->items = \DustPressHelper::get_menu_as_items( $menu_id, $parent, $override, true );
		}

		$menu->ul_classes = $ul_classes;
		$menu->ul_id = $ul_id;
		$menu->show_submenu = $show_submenu;

		// add data into debugger
		dustpress()->set_debugger_data( 'Menu', $menu );

		$output = dustpress()->render( [
			"partial" => "menu",
			"data" => $menu,
			"type" => "html",
			"echo" => false
		]);

		return $output;
	}
}

$this->add_helper( "menu", new Menu() );
//$this->dust->helpers['menu'] = new Menu();