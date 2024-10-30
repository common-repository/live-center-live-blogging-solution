<?php
/**
 * Plugin Name: Live Center - Live-Blogging Solution
 * Description: Live Center’s live publishing platform empowers editors to publish news 24/7 seamlessly and effectively. Whether these are breaking news, sports events, regional or global news, Live Center enables journalists to report from anywhere in real time.
 * Version: 1.0
 * Author: Norkon
 * Author URI: https://www.norkon.net/
 **/
 namespace LiveCenterLiveBlogNamespace;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class LiveCenterLiveBlog {

  public function __construct() {
      add_action('admin_menu', array( $this, 'lclb_extra_post_info_menu'));
      add_action('add_meta_boxes', array( $this, 'lclb_cd_meta_box_add'));
      add_action('add_meta_boxes', array( $this, 'lclb_cd_meta_box_add_page'));
      add_shortcode('livecenter', array( $this, 'lclb_livecenter_func'));
	}

  public function lclb_extra_post_info_menu()
  {
      $page_title = 'Live Center Plugin';
      $menu_title = 'Live Center Plugin';
      $capability = 'manage_options';
      $menu_slug  = 'live-center-plugin';
      $function   = array( $this, 'lclb_live_center_plugin');
      $icon_url   = 'dashicons-share-alt2';
      $position   = 4;
      add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position);
      add_submenu_page(
        $menu_slug,
        'Edit API Key',
        'Edit API Key',
        $capability,
        'edit-api-key',
        array($this, 'lclb_edit_api_key_page')
    );
  }

  public function lclb_edit_api_key_page()
{
    if (isset($_POST['api_key'])) {
        $api_key = sanitize_text_field($_POST['api_key']);
        update_option('livecenter_api_key', $api_key);
    }

    $current_api_key = get_option('livecenter_api_key', '');

    ?>
    <div class="wrap">
        <h2>Edit API Key</h2>
        <form method="post" action="">
            <input type="text" name="api_key" value="<?php echo esc_attr($current_api_key); ?>" />
            <input type="submit" class="button-primary" value="Save API Key" />
        </form>
    </div>
    <?php
}

  public function lclb_cd_meta_box_add()
  {
      add_meta_box('livecenter-metabox', 'Live Center', array( $this, 'lclb_meta_box'), 'post', 'normal', 'high');
  }

  public function lclb_cd_meta_box_add_page()
  {
      add_meta_box('livecenter-metabox', 'Live Center', array( $this, 'lclb_meta_box'), 'page', 'normal', 'high');
  }

  public function lclb_meta_box()
  {
    wp_enqueue_script('skins', plugin_dir_url(__FILE__).'skins.js');
    wp_enqueue_script('fuse.min', plugin_dir_url(__FILE__).'fuse.min.js');
    wp_enqueue_style('style', plugin_dir_url(__FILE__).'style.css');
  ?>
    <img style='width: 200px' src="<?php echo plugins_url('lc-logo.svg', __FILE__)?>" />
    <div id='livecenter-havelink'>
      <h3>Add your API link:</h3>
        <input type="text" id="livecenter-api-link"  value="" />
        <button class="livecenter-button" onclick='submitLink()'>Add link</button>
        <p id='livecenter-error-message'>Error: bad link</p>
        <a class="add-new-channel" href="./admin.php?page=live-center-plugin"><svg style='margin: 16px 8px 16px' fill="red" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm6 13h-5v5h-2v-5h-5v-2h5v-5h2v5h5v2z"/></svg>You can find your KEY here</a>
    </div>

    <div id='livecenter'>
       <h3>Select a skin:</h3>
       <div id="skin-selector"></div>

       <hr />

       <div style='display: flex'>
         <span id='livecenter-search'>
           <input type="text" id="livecenter-search-input" onkeyup="searchItem()" value="" placeholder="Search..."/>
           <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M21.172 24l-7.387-7.387c-1.388.874-3.024 1.387-4.785 1.387-4.971 0-9-4.029-9-9s4.029-9 9-9 9 4.029 9 9c0 1.761-.514 3.398-1.387 4.785l7.387 7.387-2.828 2.828zm-12.172-8c3.859 0 7-3.14 7-7s-3.141-7-7-7-7 3.14-7 7 3.141 7 7 7z"/></svg>
         </span>
         <a class="add-new-channel" href="./admin.php?page=live-center-plugin"><svg style='margin: 16px 8px 16px' fill="#088533" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm6 13h-5v5h-2v-5h-5v-2h5v-5h2v5h5v2z"/></svg>Create a new channel</a>
       </div>
        <div id='livecenter-list'></div>
    </div>
    <script>
      let skin = "";
      let allData = [];

      // Force close of meta_box
      document.getElementById('livecenter-metabox').classList.add('closed')

      function setCookie(cname, cvalue, exdays) {
          var d = new Date();
          d.setTime(d.getTime() + (exdays*24*60*60*1000));
          var expires = "expires="+ d.toUTCString();
          document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
        }

      function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length == 2) return parts.pop().split(";").shift();
      }

      const getData = (link) => {
        try {
          return fetch(link)
          .then(function(response) {
            return response.json();
          })
          .then(function(myJson) {
            return myJson.latestChannels;
          });
        } catch (e) {
          return e;
        }
      }


      function copyStringToClipboard (str) {
         var el = document.createElement('textarea');
         el.value = str;
         el.setAttribute('readonly', '');
         el.style = {position: 'absolute', left: '-9999px'};
         document.body.appendChild(el);
         el.select();
         document.execCommand('copy');
         document.body.removeChild(el);
      }

      const submitLink = async () => {
        const value = document.getElementById('livecenter-api-link').value;

        try {
          await getData(value);

          setCookie('link', value, 300);
          render();
        } catch (e) {
          console.log('Bad link');
          document.getElementById('livecenter-error-message').style.display = 'block';
        }
      }

      const renderList = (skin, data) => {
        const container = document.getElementById("livecenter-list");
        document.getElementById("livecenter-list").innerHTML = "";

        data.map(item => {
          const { created, name, id, lastUsedUtc, tenantKey, content: { commentsEnabled = false } = {} } = item;
          const el = document.createElement("div");
          el.innerHTML = `
            <div class="livecenter-list-element" id='${id}'>
              <h3 style="margin-top: 0">${name}</h3>
              <p><b>Creation date:</b> ${new Intl.DateTimeFormat().format(new Date(created * 1000))}</p>
              <p><b>Last used:</b> ${new Intl.DateTimeFormat().format(new Date(lastUsedUtc * 1000))}</p>
              <p><b>Comments:</b> ${commentsEnabled ? '<span class="livecenter-green">Enabled</span>' : '<span class="livecenter-red">Disabled</span>'}</p>
              <p class="livecenter-code">[livecenter theme="${skin}" id="${id}" tenant="${tenantKey}"]</p>
              <div class='livecenter-button-copy' onclick="liveCenterCopyToClipboard('${id}')">Copy shortcode</button>
            </div>`;
          container.appendChild(el);
        });
      }



      const liveCenterCopyToClipboard = (id) => {
        const link = document.getElementById(id)

        link.classList.toggle('livecenter-copied');

        copyStringToClipboard(link.getElementsByClassName('livecenter-code')[0].innerHTML)
        const a = link.getElementsByClassName('livecenter-code')
        setTimeout(()=> document.getElementById(id).classList.toggle('livecenter-copied'), 1000);
      }

      const searchItem = () => {
        const searchValue = document.getElementById('livecenter-search-input').value;

        if(!searchValue) return renderList(skin, allData);

        var options = {
            shouldSort: true,
            threshold: 0.3,
            location: 0,
            distance: 50,
            maxPatternLength: 32,
            minMatchCharLength: 1,
            keys: [
              "name",
            ]
          };
          var fuse = new Fuse(allData, options); // "list" is the item array
          var result = fuse.search(document.getElementById('livecenter-search-input').value);
          renderList(skin, result);
      }

      const render = async () => {
        const apiKey = getCookie('link');

        if (apiKey) {
          // document.getElementById('livecenter-havelink').style.display = 'none';
          const h3Element = document.querySelector('#livecenter-havelink h3');
          if (h3Element) {
            h3Element.textContent = 'Edit your API link:';
          }

          document.getElementById('livecenter').style.display = 'block';

          const data = await getData(apiKey);
          allData = data;

          const skinsSelect = document.getElementById('skin-selector');

          skins.map(item => {
            const { name, image, value, isDefault } = item;
            const el = document.createElement("div");
            if (isDefault) skin = value;
            el.innerHTML = `
              <div class='livecenter-skin-selector'>
                <input type="radio" name="skin" id="${name}" value="${name}" ${isDefault ? 'checked' : ''}>
                <label for="${name}"><span>${name}</span><img width="80px" src="<?php echo plugins_url('skinicons/${image}', __FILE__)?>" /></label>
              </div>
            `;

            el.addEventListener("change", () => {
              skin = value;
              renderList(value, data)
            });

            skinsSelect.appendChild(el);
          });

          renderList(skin, data);
        }
        else {
          document.getElementById('livecenter').style.display = 'none';
          // document.getElementById('livecenter-havelink').style.display = 'block';
        }
      }

      render()
    </script><?php
  }


  // [livecenter theme="dark" id="1"]
  public function lclb_livecenter_func($atts)
  {
      $a = shortcode_atts(array(
          'theme' => 'default',
          'id' => '',
          'tenant' => '',
      ), $atts);

      return "<div class=\"live-center-embed\" data-src=\"https://livecenter.norkon.net/frame/" . esc_html($a['tenant']) . "/" . esc_html($a['id']) . "/" . esc_html($a['theme']) . "\"><script type=\"text/javascript\">var NcCore;!function(d){if(!d.frameRequestHandler){d.frameRequestHandlers=d.frameRequestHandlers||{};var e=window.addEventListener?\"addEventListener\":\"attachEvent\",t=window[e],n=\"attachEvent\"==e?\"onmessage\":\"message\";d.frameRequestHandler=function(e){var t=null,a=!1;try{var n=e[e.message?\"message\":\"data\"];if(\"ncfunc-req:\"!==n.substr(0,11))return;var r=n.substr(11),s=s=JSON.parse(r);if(s.key){var i=d.frameRequestHandlers[s.key];if(i){try{t=s.args?i.apply(null,s.args):i()}catch(e){a=!0}-1!==s.seq&&(t&&t.then?t.then(function(e){o(e)}).catch(function(e){a=!0,o(e)}):o(t))}}}catch(e){return}function o(e){var t=s.frameCount||1e3,n=document.getElementById(\"nc-frame-c-\"+t);n&&n.firstChild.contentWindow.postMessage(\"ncfunc-res:\"+JSON.stringify({seq:s.seq,lane:s.lane,d:e,failed:a}),\"*\")}},t(n,d.frameRequestHandler)}}(NcCore||(NcCore={}),window.parent),NcCore.frameRequestHandlers={test:function(){return\"It works!\"},getUserName:function(){return(window.ais?window.ais.userName:null)||\"\"}},function(){var e=window.ncVizCounter||1e3,a=e+\"\";window.ncVizCounter=e+1;var t=\"nc-frame-c-\"+e;document.write('<div style=\"position: relative; height: 300px; border: none;\" id=\"'+t+'\" ><iframe src=\"https://livecenter.norkon.net/frame/" . $a['tenant']. "/" . $a['id']. "/" . $a['theme']. "?'+e+'\" style = \"position: absolute; left: 0; top: 0; width: 100%; height: 100%;\" frameborder=\"0\" > </iframe></div >');var n=window.addEventListener?\"addEventListener\":\"attachEvent\",r=window[n],s=\"attachEvent\"==n?\"onmessage\":\"message\",i=document.getElementById(t);r(s,function(e){var t=e[e.message?\"message\":\"data\"]+\"\";if(console.log(t),\"nc:\"===t.substr(0,3)){var n=t.split(\":\");n[1]===a&&\"h\"===n[2]&&(i.style.height=n[3]+\"px\")}},!1)}();</script></div>";
  }

  public function lclb_live_center_plugin()
  {
    ?><div style="height: 100vh"><iframe src="https://livecenter.norkon.net/" height="100%" width="100%"></iframe></div><?php
  }
}

new LiveCenterLiveBlog();

?>
