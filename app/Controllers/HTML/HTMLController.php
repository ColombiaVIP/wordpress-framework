<?php
/**
 * Campos HTML del formulario. Cada valor sale escapado.
 */

namespace WordpressFramework\Controllers\HTML;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HTMLController {

	/**
	 * $fields es HTML ya construido por los otros métodos de esta clase.
	 */
	public static function form( $fields, $class, $action ) {
		ob_start();
		?>
		<form method="post" class="cvipForm <?php echo esc_attr( (string) $class ); ?>" action="<?php echo esc_url( (string) $action ); ?>">
			<?php echo $fields; ?>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function hidden( $name, $value ) {
		ob_start();
		?>
			<input type="hidden" name="<?php echo esc_attr( (string) $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
		<?php
		return ob_get_clean();
	}

	public static function text( string $name, string $value, ?string $required, ?string $readonly ) {
		ob_start();
		?>
			<input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo self::bandera( $required, 'required' ); ?><?php echo self::bandera( $readonly, 'readonly' ); ?>>
		<?php
		return ob_get_clean();
	}

	public static function textArea( string $name, string $value, ?string $required, ?string $readonly ) {
		unset( $required, $readonly );
		ob_start();
		if ( preg_match( '/^[A-Za-z0-9_\-\[\]]+$/', $name ) === 1 ) {
			$settings = array(
				'media_buttons' => false,
				'quicktags'     => true,
				'textarea_name' => $name,
			);
			wp_editor( $value, sanitize_title( $name ), $settings );
		}
		return ob_get_clean();
	}

	/**
	 * $text puede ser el HTML del control, generado por esta misma clase.
	 * El título del campo sí se escapa.
	 */
	public static function label( string $for, string $text ) {
		ob_start();
		?>
			<label><?php echo esc_html( $for ); ?>:<?php echo $text; ?></label>
		<?php
		return ob_get_clean();
	}

	public static function submit( string $value ) {
		ob_start();
		?>
			<input type="submit" value="<?php echo esc_attr( $value ); ?>">
		<?php
		return ob_get_clean();
	}

	public static function select( string $name, array $options, string $selected ) {
		ob_start();
		?>
			<select name="<?php echo esc_attr( $name ); ?>">
				<?php foreach ( $options as $option ) : ?>
					<?php $option = (string) $option; ?>
					<option value="<?php echo esc_attr( $option ); ?>"<?php echo $option === $selected ? ' selected' : ''; ?>><?php echo esc_html( $option ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php
		return ob_get_clean();
	}

	public static function media( $key, $name, $field, $required ) {
		$key = is_string( $key ) ? $key : '';
		if ( preg_match( '/^[A-Za-z0-9_-]+$/', $key ) !== 1 ) {
			return '';
		}

		wp_enqueue_media();
		$value    = isset( $field['Value'] ) ? (string) $field['Value'] : '';
		$required = self::bandera( is_string( $required ) ? $required : '', 'required' );
		ob_start();
		?>
			<div class="mediaUpload">
				<input type="hidden" class="myfile <?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( (string) $name ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="UPLOAD YOUR IMAGE"<?php echo $required; ?> >
				<div class="imageList<?php echo esc_attr( $key ); ?>">
				<?php foreach ( explode( ',', $value ) as $image ) : ?>
					<?php $image = trim( $image ); ?>
					<?php if ( $image === '' ) { continue; } ?>
					<img src="<?php echo esc_url( $image ); ?>" width="100px" alt="">
				<?php endforeach; ?>
				</div>

				<button class="upload upload_<?php echo esc_attr( $key ); ?>">SUBIR</button>
				<button class="remove remove_<?php echo esc_attr( $key ); ?>">&times;</button>

				<script type="text/javascript">
					jQuery(document).ready(function($) {
					$('.upload_<?php echo esc_attr( $key ); ?>').click(function() {
						wp.media.editor.open(button);
						var button = $(this);
						var websiteName = window.location.protocol+"//"+window.location.host;
						$('.imageList<?php echo esc_attr( $key ); ?>').text("");
						var relativeUrls = [];
						wp.media.editor.send.attachment = function(props, attachment) {
							var relativeUrl=attachment.url.replace(websiteName, "");
							$('.imageList<?php echo esc_attr( $key ); ?>').append(
									"<img src='"+relativeUrl+"' width='100px'>"
							);
							relativeUrls.push(relativeUrl);
							$('.<?php echo esc_attr( $key ); ?>').val(relativeUrls.join());
						}
						return false;
					});

					$('.remove_<?php echo esc_attr( $key ); ?>').click(function() {
						var answer = confirm('Seguro?');
						if (answer == true) {
						$('.<?php echo esc_attr( $key ); ?>').val("").text("");
						$('.imageList<?php echo esc_attr( $key ); ?>').text("");
						}
						return false;
					});
					});
				</script>
			</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Solo se imprime el atributo si el valor es exactamente el permitido.
	 */
	private static function bandera( ?string $valor, string $permitido ): string
	{
		return $valor === $permitido ? ' ' . $permitido : '';
	}
}
