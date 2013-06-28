<?php
/*
Plugin Name: PennyRead for WordPress
Plugin URI:  http://pennyread.com
Description: Bring PennyRead to your articles
Version:     1.0
Author:      Greg Deback, eko &co
Author URI:  http://eko.co
*/

class WP_PennyRead {


  /**
   * Internal
   */
  private $path; /// Plugin abs path
  private $url;  /// Plugin base URL

  //define('WPLANG', 'fr_FR');

  private $fields = array(
    'active' => array('check', false),
    'editor' => array('text', ''),
    'lang'   => array('list', 'global', array('global', 'FR', 'EN', 'ES', 'DE')),
    'cur'    => array('list', 'global', array('global', 'EUR', 'USD', 'GBP', 'RUB', 'CAD', 'AUD', 'JPY', 'CHF')),
    'price'  => array('text', '0.15'),
    'video'  => array('check', false)
  );
  private $vFields = array(
    'timer'  => array('list', '2', array('1', '2', '5')),
    'inset'  => array('list', '1in', array('1in', '2in')),
    'width'  => array('text', '560'),
    'height' => array('text', '315')
  );
  private $options = array(
    'editor' => array('text', 'PR-pennyReadKey'),
    'lang'   => array('list', 'FR', array('FR', 'EN')),
    'cur'    => array('list', 'USD', array('EUR', 'USD', 'GBP', 'RUB', 'CAD', 'AUD', 'JPY', 'CHF')),
    'mode'   => array('list', 'button', array('button', 'sms'))
  );


  /**
   * Register plugin
   */
  public function __construct() {
    $this->path = dirname(plugin_basename(__FILE__));
    $this->url  = plugins_url('', __FILE__);
    // Locale
    load_plugin_textdomain('pennyread', false, $this->path . '/langs');
    // WordPress 'hooks'
    add_action('wp_head',              array($this, 'head'));
    add_action('wp_enqueue_scripts',   array($this, 'script'));
    add_action('admin_menu',           array($this, 'menu'));
    add_action('admin_init',           array($this, 'init'));
    add_action('save_post',            array($this, 'save'));
    // Editor plugin & button
    add_filter('wp_insert_post_data' , array($this, 'update'));
    add_filter('mce_external_plugins', array($this, 'plugins'));
    add_filter('mce_buttons',          array($this, 'buttons'));
    add_filter('mce_css',              array($this, 'css'));
    // Shortcode (separator)
    add_shortcode('pennyread',         array($this, 'insert'));
  }


  /**
   * Complete head
   */
  public function head() {
    if (is_admin()) return;
    global $post;
    if (!$post) return;
    $custom = get_post_custom($post->ID);
    if (!isset($custom['pennyread-active']) ||
        !$custom['pennyread-active'][0]) {
?>
      <style type="text/css">.penny-read-more { display: none; }</style>
<?php
      return;
    }
    $editor = get_option('pennyread-editor');
    if (is_single() &&
        isset($custom['pennyread-editor']) &&
        $custom['pennyread-editor'][0])
      $editor = $custom['pennyread-editor'][0];
?>
      <style type="text/css">
        .pennyread, .penny-read-more { display: none; }
      </style>
      <script type="text/javascript">
        var pennyReadEditorId = "<?php echo $editor; ?>";
<?php if (isset($_GET['prdbg'])): ?>
        var pennyReadDebug    = true;
        var pennyReadHome     = "http://localhost:3000";
<?php else: ?>
        var pennyReadHome     = "http://www.pennyread.com";
<?php endif; ?>
        var pennyReadWidget   = "Button";
      </script>
<?php
  }


  /**
   * Enqueue external PennyRead JS
   */
  public function script() {
    if (is_admin()) return;
    // $src = 'http://www.pennyread.com/en/js/pennyread.js';
    $src = (isset($_GET['prloc']))? 'http://localhost:3000': 'http://www.pennyread.com';
    $src .= '/en/js/pennyread.js';
    wp_register_script('pennyread',            /// Handle
                       $src,                   /// Source URL
                       array(),                /// Dependencies
                       '1.0',                  /// Version
                       true);                  /// In footer?
    wp_enqueue_script('pennyread');
  }


  /**
   * Append PennyRead menu entry
   */
  public function menu() {
    add_options_page('Penny Read - WP Plugin', /// Page title
                     'Penny Read',             /// Menu title
                     // 'manage_options',         /// Capabilities
                     'publish_posts',          /// Capabilities
                     'wp_pennyread_options',   /// Menu slug
                     array($this, 'options')); /// Callback
  }


  /**
   * Init PennyRead meta-box
   */
  public function init() {
    add_meta_box('wp_pennyread',               /// ID
                 'Penny Read',                 /// Box name
                 array($this, 'meta'),         /// Callback
                 'post',                       /// Post type
                 'normal',                     /// Box place (normal/side)
                 'high');                      /// Priority
  }


  /**
   * Edit PennyRead options
   */
  public function options() {
?>
<style>
.pennyread-options .field { width: 50%; }
.pennyread-options label { display: block; font-weight: bold; }
.pennyread-options .tip { display: none; position: absolute; font-size: .9em; color: #aaa; }
.pennyread-options tr:hover .tip { display: block; }
.pennyread-icon { background-image: url(<?php echo $this->url; ?>/icons/box.png); width: 48px; }
</style>
<div class="wrap">
  <div class="pennyread-icon icon32"><br /></div>
  <h2><?php echo __("optionsTitle", "pennyread"); ?></h2>
  <form method="post" action="options.php">
    <?php wp_nonce_field('update-options'); ?>
    <p><?php echo __("optionsIntro", "pennyread"); ?></p>
    <table class="form-table pennyread-options">
<?php
    $keys = array();
    foreach ($this->options as $key=>$field) {
      $prkey = 'pennyread-' . $key;
      $keys[] = $prkey;
      $value = get_option($prkey)? get_option($prkey): $field[1];
      $this->option($key, $field, $value);
    }
?>
    </table>
    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="page_options"
           value="<?php echo implode(', ', $keys); ?>" />
    <p class="submit">
      <input type="submit" name="submit" id="submit" class="button button-primary"
             value="<?php echo __("optionsSave", "pennyread"); ?>"  />
    </p>
  </form>
</div>
<?php
  }


  /**
   * Insert PennyRead meta-box
   */
  public function meta() {
    global $post;
    $custom = get_post_custom($post->ID);
    $video  = isset($custom['pennyread-video']) && $custom['pennyread-video'][0];
    $block  = $video? 'table': 'none';
?>
<style>
.pennyread-meta .field { width: 90%; }
.pennyread-meta label { display: block; font-weight: bold; }
.pennyread-meta .tip { display: none; position: absolute; font-size: .9em; color: #aaa; }
.pennyread-meta tr:hover .tip { display: block; }
</style>
<script type="text/javascript">
function pennyread_video(chk) {
document.getElementById('pennyread-video-meta').style.display = chk.checked? 'table': 'none';
}
</script>
    <p><?php echo __("metaAbout", "pennyread"); ?></p>
    <table class="form-table pennyread-meta">
<?php
    foreach ($this->fields as $key=>$field) {
      $prkey = 'pennyread-' . $key;
      $value = isset($custom[$prkey])? $custom[$prkey][0]: $field[1];
      if ($value == 'global') $value = get_option($prkey);
      $this->option($key, $field, $value);
    }
?>
    </table>
    <table class="form-table pennyread-meta" id="pennyread-video-meta"
           style="display: <?php echo $block; ?>">
<?php
    foreach ($this->vFields as $key=>$field) {
      $prkey = 'pennyread-' . $key;
      $value = isset($custom[$prkey])? $custom[$prkey][0]: $field[1];
      $this->option($key, $field, $value);
    }
?>
    </table>
<?php
  }


  /**
   * Save post and update PennyRead options
   */
  public function save($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
      return $post_id;
    global $post;
    if (!$post || $post->post_type != "post") return;
    foreach ($this->fields as $key=>$field) {
      $prkey = 'pennyread-' . $key;
      $value = $_POST[$prkey];
      if ($key == 'price')
        $value = floatval(str_replace(',', '.', $value));
      if ($value == 'global')
        $value = get_option($prkey);
      update_post_meta($post->ID, $prkey, $value);
    }
  }
  function update($data) {
    if (!isset($data['post_content']) ||
        !isset($_POST['pennyread-price']))
      return $data;
    $html  = &$data['post_content'];
    $match = preg_match_all('/\s*<div (.+?pennyread.+?)>\s*' .
                            '<span (.+?penny\-?read.+?)>(.+?)<\/span>\s*' .
                            '(<\/div>)?/i',
                            $html, $matches, PREG_SET_ORDER);
    if (!$match) return $data;
    $atts = $this->atts();
    foreach ($matches as $i=>$match) {
      $content = $this->format(null, $atts);
      $html = str_replace($match[0], "\n\n" . $content . "\n\n", $html);
      if ($match[4]) $html .= '</div>';
    }
    return $data;
  }


  /**
   * TinyMCE plugin & button
   */
  public function plugins($plugins) {
    $plugins['pennyread'] = $this->url . '/wp-pennyread.js';
    return $plugins;
  }
  public function buttons($buttons) {
    array_push($buttons, 'separator', 'pennyread');
    return $buttons;
  }
  function css($css) {
    if ($css) $css .= ',';
    $css .= $this->url . '/wp-pennyread.css';
    return $css;
  }


  /**
   * Insert 
   */
  public function insert($atts, $content = null) {
    // Future feature: pass options via attributes
    return $this->format($content, $atts, true);
  }


  /**
   * Format
   */
  private function atts() {
    $atts = array();
    $strip = get_magic_quotes_gpc();
    foreach ($_POST as $prkey=>$value) {
      if (strpos($prkey, 'pennyread-') !== 0) continue;
      $key = substr($prkey, 10);
      if ($key == 'price')
        $value = floatval(str_replace(',', '.', $value));
      if ($value == 'global')
        $value = get_option($prkey);
      $atts[$key] = $strip? stripslashes($value): $value;
    }
    return $atts;
  }
  private function format($html, $atts, $close = false) {
    $option = $atts['cur'] . $atts['price'] .
              ' lang=' . $atts['lang'];
    if ($atts['video']) {
      $option .= ' timer='  . $atts['timer'] .
                 ' inset='  . $atts['inset'] .
                 ' width='  . $atts['width'] .
                 ' height=' . $atts['height'];
    }
    $body = ($html || $close)? $html . '</div>': '';
    return '<div class="pennyread" data-pennyread="' . $option . '">' .
           '<span class="penny-read-more">' .
           sprintf(__("readMoreFor", "pennyread"), $atts['price'], $atts['cur']) .
           '</span>' . $body;
  }
  private function option($key, $field, $value) {
    $prkey = 'pennyread-' . $key;
    $idkey = 'id="' . $prkey . '" name="' . $prkey . '"';
?>
      <tr>
        <th class="pennyread-<?php echo $key; ?>">
          <label for="pennyread-<?php echo $key; ?>">
            <?php echo __($key . "Label", "pennyread"); ?></label>
          <span class="tip">
            <?php echo __($key . "Tip", "pennyread"); ?></span>
        </th>
        <td class="pennyread-<?php echo $key; ?>">
<?php   if ($field[0] == 'check'): ?>
          <input type="checkbox" <?php echo $idkey; ?> value="1"
            <?php if ($key == 'video') echo "onclick='pennyread_video(this)' "; ?>
            <?php if ($value == 1) echo "checked='checked'"; ?> />
<?php   elseif ($field[0] == 'list'): ?>
          <select class="field" <?php echo $idkey; ?>>
<?php     foreach ($field[2] as $item): ?>
            <option value="<?php echo $item; ?>"
              <?php if ($value == $item) echo "selected='selected'"; ?>>
              <?php echo __($key . "-" . $item, "pennyread"); ?>
            </option>
<?php     endforeach; ?>
          </select>
<?php   else: ?>
          <input class="field" type="text" <?php echo $idkey; ?>
                 value="<?php echo esc_attr($value); ?>" />
<?php   endif; ?>
        </td>
      </tr>
<?php
  }


}

new WP_PennyRead();
