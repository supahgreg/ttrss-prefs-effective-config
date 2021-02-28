<?php
class Prefs_Effective_Config extends Plugin {

  private $host;
  private const CONFIG_KEYS_TO_MASK = ['DB_PASS'];
  private const PARAM_TYPE_TO_NAME = [
    Config::T_BOOL => 'boolean',
    Config::T_STRING => 'string',
    Config::T_INT => 'integer',
  ];

  function about() {
    return [
      0.3, // version
      'Shows your effective tt-rss config @ Preferences --> System', // description
      'wn', // author
      false, // is system
      'https://github.com/supahgreg/ttrss-prefs-effective-config/', // more info URL
    ];
  }

  function api_version() {
    return 2;
  }

  function init($host) {
    $this->host = $host;
    $host->add_hook($host::HOOK_PREFS_TAB, $this);
  }

  function hook_prefs_tab($tab) {
    if ($tab != 'prefSystem' || !self::is_admin()) {
      return;
    }
?>
    <div dojoType='dijit.layout.AccordionPane' title='<i class="material-icons">subject</i> <?= __('Effective Config') ?>'>
          <script type='dojo/method' event='onSelected' args='evt'>
            if (!this.domNode.querySelector('.loading')) {
              return;
            }

            window.setTimeout(() => {
              xhr.json('backend.php', {op: 'pluginhandler', plugin: 'prefs_effective_config', method: 'get_effective_config'}, (reply) => {
                this.attr('content', `
                <style type='text/css'>
                  #config-items-list { text-align: left; border-spacing: 0; }
                  #config-items-list .redacted { opacity: 0.5; }
                  #config-items-list th { border-bottom: 1px solid #000; }
                  #config-items-list tbody tr:hover { background: #eee; }
                  #config-items-list tbody td { padding: 5px; }
                  #config-items-list td:not(:last-child) { border-right: 1px solid #ccc; }
                </style>

                <table id='config-items-list'>
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Effective Value</th>
                      <th>Environment Variable Value</th>
                      <th>Default Value</th>
                      <th>Type Hint</th>
                    </tr>
                  </thead>
                  <tbody>
                  ${
                    reply.map(param => `
                      <tr>
                        <td>${param['name']}</td>
                        ${param['should_redact'] ? `<td class='redacted'>redacted</td>` : `<td>${param['effective_val']}</td>`}
                        ${param['should_redact'] ? `<td class='redacted'>${param['env_val']}</td>` : `<td>${param['env_val']}</td>`}
                        <td>${param['default_val']}</td>
                        <td>${param['type_hint']}</td>
                      </tr>
                    `).join('')
                  }
                  </tbody>
                </table>
                `);
              });
            }, 200);
          </script>
          <span class='loading'><?= __('Loading, please wait...') ?></span>
      </div>
<?php
  }

  function get_effective_config() {
    if (!self::is_admin()) {
      print format_error('Access forbidden.');
      return;
    }

    $cfg_instance = new Config();
    $cfg_rc = new ReflectionClass($cfg_instance);

    $cfg_constants = $cfg_rc->getConstants();
    $envvar_prefix = $cfg_rc->getConstant('_ENVVAR_PREFIX');
    $defaults = $cfg_rc->getConstant('_DEFAULTS');

    $params = $cfg_rc->getProperty('params');
    $params->setAccessible(true);

    $ret = [];

    foreach ($params->getValue($cfg_instance) as $p => $v) {
      list ($pval, $ptype) = $v;
      $env_val = getenv($envvar_prefix . $p);
      list ($defval, $deftype) = $defaults[$cfg_rc->getConstant($p)];
      $should_redact = in_array($p, self::CONFIG_KEYS_TO_MASK);

      $ret[] = [
        'name' => $envvar_prefix . $p,
        'should_redact' => $should_redact,
        'effective_val' => $should_redact ? 'redacted' : $pval,
        'env_val' => $env_val ? $should_redact ? 'redacted' : $env_val : '',
        'default_val' => $defval,
        'type_hint' => self::PARAM_TYPE_TO_NAME[$ptype],
      ];
    }

    print json_encode($ret);
  }

  private function is_admin() {
    return ($_SESSION['access_level'] ?? 0) >= 10;
  }
}
