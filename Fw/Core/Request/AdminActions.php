<?php
/**
 * Nonce y URL de las acciones de menú (parámetro option).
 *
 * El nonce es opcional (opt-in): solo se verifica para los métodos que el controlador
 * lista en $nonce. Los enlaces y formularios del framework (ListTableController,
 * FormController) ya lo incluyen siempre, así activar $nonce no exige cambiar vistas.
 */

namespace Fw\Core\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminActions
{
	public const NONCE_FIELD = '_wpfw_nonce';

	public static function nonceAction( string $page, string $option ): string
	{
		return 'wpfw_option_' . $page . '_' . $option;
	}

	/**
	 * Slug de página de administración: letras, números, guion y guion bajo.
	 */
	public static function sanitizePage( string $page ): ?string
	{
		$page = trim( $page );
		if ( preg_match( '/^[A-Za-z0-9_-]+$/', $page ) !== 1 ) {
			return null;
		}

		return $page;
	}

	public static function currentPage(): ?string
	{
		if ( ! isset( $_GET['page'] ) || ! is_string( $_GET['page'] ) ) {
			return null;
		}

		return self::sanitizePage( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Enlace GET (listados, borrar, editar) con el nonce en la query.
	 *
	 * @param array<string, scalar> $args
	 */
	public static function url( string $page, string $option, array $args = array() ): string
	{
		$args['page']   = $page;
		$args['option'] = $option;
		$url            = add_query_arg( $args, admin_url( 'admin.php' ) );

		return wp_nonce_url( $url, self::nonceAction( $page, $option ), self::NONCE_FIELD );
	}

	/**
	 * URL de un formulario POST. El nonce viaja en el cuerpo, no en el referer.
	 */
	public static function postUrl( string $page, string $option ): string
	{
		return add_query_arg(
			array(
				'page'   => $page,
				'option' => $option,
			),
			admin_url( 'admin.php' )
		);
	}

	public static function field( string $page, string $option ): string
	{
		return wp_nonce_field( self::nonceAction( $page, $option ), self::NONCE_FIELD, true, false );
	}

	public static function verify( string $page, string $option ): bool
	{
		if ( ! isset( $_REQUEST[ self::NONCE_FIELD ] ) || ! is_string( $_REQUEST[ self::NONCE_FIELD ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST[ self::NONCE_FIELD ] ) );

		return (bool) wp_verify_nonce( $nonce, self::nonceAction( $page, $option ) );
	}
}
