#!/usr/bin/env python3
"""
build-v026.py

slp_avalon v0.0.25 -> v0.0.26, PART 1 OF 3.

WHAT THIS RELEASE DOES
----------------------
Part 1 is storage and nothing else. No network call, no REST route, no
shortcode, no rendering. It exists so that the table Parts 2 and 3 depend
on is present, version-gated and proven before anything queries it.

1. A plugin-owned table, {prefix}avalon_dealer_places, created through
   dbDelta. One row per address key. It holds place_id indefinitely and
   hours with a fetched_at timestamp, which is the store the 30-day cap
   is enforced against - rev33 s0.186.

2. A SCHEMA VERSION GATE, which is the part the authorised scope did not
   have. rev33 s3.1 specified "dbDelta on activation".
   register_activation_hook fires when a plugin is activated and at no
   other time. slp_avalon is already active on Aura DEV and the deploy
   path is an SFTP overwrite, which re-activates nothing. Activation-only
   installation would therefore create this table in no environment
   currently in play, and Part 3's endpoint would query a table that does
   not exist. s0.189.

   So: avalon_hours_maybe_install() on init priority 1, comparing an
   autoloaded option against a class constant, AND SLP_Avalon::activate()
   filled in for the fresh-install case. Both, not either.

3. avalon_hours_config(), in the shape avalon_import_config() already
   established - s0.192. The two TTLs are clamped rather than merely
   defaulted, because a constant is something a person can set to 60 and
   the Places cache cap is not negotiable.

WHAT PART 1 DELIBERATELY DOES NOT DO
------------------------------------
It does not derive the address key in PHP. resolve-placeids.py computes
dealer_key through norm_text, norm_street, norm_state and norm_postal -
NFKD decomposition, a directionals map, a suffix map, three highway
regexes and a unit-token extractor with lookahead. A PHP port of that is
roughly 150 lines whose only oracle is "does it agree with Python", and a
silent disagreement produces a table that never joins to placeids.json.
That port is Part 2 work and it gets a differential test against the
resolver's own output. Part 1 declares address_key char(12) and says
nothing about who computes it. s0.193.

ENCODING
--------
ISO-8859-1 throughout with newline='' so CRLF and any high bytes survive
byte-for-byte. Both inputs are CRLF; both outputs must stay CRLF, and the
CR count is printed for each so a mangled write cannot pass quietly.

slp_avalon.js is NOT an input and NOT an output. It is untouched at Part
1 and its rev33 pin stands unchanged.

Usage:  python build-v026.py <src_dir> <out_dir>

        src_dir must hold the two v0.0.25 files, flat:
            class.slp_avalon.php
            slp_avalon.php
"""

import hashlib
import io
import os
import sys

CRLF = "\r\n"

PINS = {
    'class.slp_avalon.php': ('b51511918f23e0c32015673a1b844bf8', 122119),
    'slp_avalon.php':       ('c345671ba7067d114a43a3145aae93a4', 1808),
}


def read_exact(path):
    raw = io.open(path, 'rb').read()
    return raw.decode('iso-8859-1'), hashlib.md5(raw).hexdigest(), len(raw)


def write_exact(path, text):
    raw = text.encode('iso-8859-1')
    io.open(path, 'wb').write(raw)
    return hashlib.md5(raw).hexdigest(), len(raw), raw.count(b'\r')


def sub_once(text, old, new, label):
    n = text.count(old)
    if n != 1:
        sys.exit("ABORT {}: anchor matched {} times, expected exactly 1".format(label, n))
    print("  patched   {}".format(label))
    return text.replace(old, new, 1)


def check(cond, label):
    if not cond:
        sys.exit("ABORT self-check: {}".format(label))
    print("  check OK  {}".format(label))


def block(lines):
    """Join source lines with CRLF and terminate with CRLF."""
    return CRLF.join(lines) + CRLF


# ===========================================================================
# 1. Class constants
# ===========================================================================

CONST_ANCHOR = (
    "    class SLP_Avalon{" + CRLF +
    "        private static $instance;" + CRLF
)

CONST_BLOCK = block([
    "    class SLP_Avalon{",
    "        private static $instance;",
    "",
    "        /**",
    "         * v0.0.26 Part 1. Schema version for the dealer-places table.",
    "         *",
    "         * Bumped whenever the CREATE TABLE in avalon_hours_install()",
    "         * changes. avalon_hours_maybe_install() compares this against",
    "         * the stored option and runs dbDelta when they differ, which is",
    "         * what makes an SFTP overwrite of an already-active plugin",
    "         * install a schema change. A version constant that is never",
    "         * bumped is the same defect as no gate at all.",
    "         *",
    "         * HOURS_DB_OPTION is stored AUTOLOADED, deliberately, and it is",
    "         * the only option in this plugin that should be. It is read on",
    "         * every request by the gate, so autoload is what makes the gate",
    "         * cost a string compare instead of a query. Contrast",
    "         * avalon_geocode_cache and avalon_geocode_overrides, both",
    "         * written with an explicit 'no' because they are large and read",
    "         * only during an import.",
    "         */",
    "        const HOURS_DB_VERSION = '1';",
    "        const HOURS_DB_OPTION  = 'avalon_hours_db_version';",
])


# ===========================================================================
# 2. activate()
# ===========================================================================

ACTIVATE_ANCHOR = (
    "        public static function activate(){" + CRLF +
    CRLF +
    "        }" + CRLF
)

ACTIVATE_BLOCK = block([
    "        public static function activate(){",
    "            //v0.0.26 Part 1. Fresh installs only.",
    "            //",
    "            //register_activation_hook fires when a plugin is activated",
    "            //and at no other time. Every environment this plugin is",
    "            //deployed to already has it active, and the deploy is an",
    "            //SFTP overwrite of the files in place - nothing",
    "            //re-activates. This call alone would create the table on no",
    "            //site currently in play.",
    "            //",
    "            //avalon_hours_maybe_install() on init is what actually",
    "            //installs the table on an existing site. This is here so a",
    "            //genuinely new install has its table before the first init",
    "            //rather than one request later.",
    "            self::avalon_hours_install();",
    "        }",
])


# ===========================================================================
# 3. Hook registration
# ===========================================================================

HOOK_ANCHOR = (
    "            add_filter('rest_post_dispatch',array(self::$instance,"
    "'avalon_rest_strip_keys'),999,3);" + CRLF
)

HOOK_BLOCK = block([
    "            add_filter('rest_post_dispatch',array(self::$instance,"
    "'avalon_rest_strip_keys'),999,3);",
    "            // SLP Dealer Guard, hours storage. v0.0.26 Part 1.",
    "            //",
    "            // Priority 1 on init, not activation. See activate() for",
    "            // why activation cannot carry this on its own.",
    "            //",
    "            // Priority 1 rather than the default 10 so the table is in",
    "            // place before anything registered later can query it. It",
    "            // does not race SLP's post-type registration at 11 because",
    "            // it does not touch SLP - it reads one option and, on all",
    "            // but the first request after a schema bump, returns.",
    "            //",
    "            // add_action and not add_filter: this returns nothing and",
    "            // nothing consumes a return value.",
    "            add_action('init', array(self::$instance,"
    "'avalon_hours_maybe_install'), 1);",
])


# ===========================================================================
# 4. The methods
# ===========================================================================

METHODS_ANCHOR = "        public function avalon_rest_protected_slugs(){" + CRLF

METHODS_BLOCK = block([
    "        /**",
    "         * v0.0.26 Part 1. The dealer-places table name.",
    "         *",
    "         * $wpdb->prefix and not base_prefix: each brand site has its own",
    "         * database and its own cache, which is the arithmetic s0.186",
    "         * ran - roughly 600 to 900 Enterprise calls a month across three",
    "         * sites against a 1,000 allowance, not 303 shared.",
    "         */",
    "        public static function avalon_hours_table(){",
    "            global $wpdb;",
    "            return $wpdb->prefix . 'avalon_dealer_places';",
    "        }",
    "",
    "        /**",
    "         * v0.0.26 Part 1. Create or migrate the dealer-places table.",
    "         *",
    "         * dbDelta is not SQL-tolerant and every constraint below is load",
    "         * bearing:",
    "         *",
    "         *   - TWO spaces after PRIMARY KEY. One space and dbDelta does",
    "         *     not recognise the line as a key at all.",
    "         *   - One field per line. It parses by line, not by comma.",
    "         *   - KEY, never INDEX.",
    "         *   - LOWERCASE type names. dbDelta compares this text against",
    "         *     DESCRIBE output, which MySQL returns lowercase. Uppercase",
    "         *     types make it issue the same ALTER TABLE on every run,",
    "         *     forever, and nothing reports it.",
    "         *   - No index prefix lengths. dbDelta mishandles them and can",
    "         *     re-add the same index indefinitely, which is why place_id",
    "         *     is varchar(191) and indexed whole rather than varchar(255)",
    "         *     indexed at (64). Google publishes no maximum place ID",
    "         *     length; 191 is the utf8mb4 index-safe width and every ID",
    "         *     the resolver has produced is far shorter.",
    "         *   - No CURRENT_TIMESTAMP defaults and no zero dates. MySQL 5.7",
    "         *     and 8.0 reject '0000-00-00' under the strict mode they",
    "         *     default to. Every timestamp here is written by PHP, in",
    "         *     GMT, with current_time('mysql', true).",
    "         *",
    "         * The SQL is assembled with implode(\"\\n\", ...) rather than",
    "         * written as a literal. This file is CRLF; a literal would carry",
    "         * CRLF into the statement, and while dbDelta does trim \\r the",
    "         * dependence would be invisible and one reformat from breaking.",
    "         */",
    "        public static function avalon_hours_install(){",
    "            global $wpdb;",
    "",
    "            $table   = self::avalon_hours_table();",
    "            $collate = $wpdb->get_charset_collate();",
    "",
    "            $sql = implode(\"\\n\", array(",
    "                \"CREATE TABLE {$table} (\",",
    "                \"  address_key char(12) not null,\",",
    "                \"  sl_id bigint(20) unsigned null default null,\",",
    "                \"  place_id varchar(191) null default null,\",",
    "                \"  place_status varchar(16) not null default 'pending',\",",
    "                \"  place_checked_at datetime null default null,\",",
    "                \"  hours_json longtext null default null,\",",
    "                \"  hours_status varchar(16) not null default 'pending',\",",
    "                \"  fetched_at datetime null default null,\",",
    "                \"  primary_type_display varchar(190) null default null,\",",
    "                \"  locality varchar(190) null default null,\",",
    "                \"  admin_area varchar(190) null default null,\",",
    "                \"  attribution_json text null default null,\",",
    "                \"  error_count smallint(5) unsigned not null default 0,\",",
    "                \"  last_error varchar(190) null default null,\",",
    "                \"  updated_at datetime null default null,\",",
    "                \"  PRIMARY KEY  (address_key),\",",
    "                \"  KEY sl_id (sl_id),\",",
    "                \"  KEY place_id (place_id),\",",
    "                \"  KEY hours_sweep (hours_status, fetched_at),\",",
    "                \"  KEY place_sweep (place_status, place_checked_at)\",",
    "                \") {$collate};\"",
    "            ));",
    "",
    "            require_once ABSPATH . 'wp-admin/includes/upgrade.php';",
    "            $changes = dbDelta( $sql );",
    "",
    "            //A no-op dbDelta returns an empty array. Logging only on",
    "            //change keeps the PHP log quiet on the ordinary path and",
    "            //leaves a record of the one request that migrated.",
    "            if ( ! empty( $changes ) ) {",
    "                self::log( 'hours schema ' . self::HOURS_DB_VERSION"
    " . ': ' . print_r( $changes, true ) );",
    "            }",
    "",
    "            update_option( self::HOURS_DB_OPTION, self::HOURS_DB_VERSION );",
    "        }",
    "",
    "        /**",
    "         * v0.0.26 Part 1. The schema gate. s0.189.",
    "         *",
    "         * Runs on init priority 1 on every request. On all but the first",
    "         * after a deploy it reads one autoloaded option, compares two",
    "         * short strings and returns.",
    "         *",
    "         * The WP_INSTALLING guard matters because WordPress sets that",
    "         * constant during its own install and upgrade routines, where",
    "         * the options table may not be in a state worth trusting and",
    "         * DDL from a plugin is unwelcome.",
    "         *",
    "         * No lock. dbDelta is idempotent and two concurrent requests",
    "         * racing it produce the same table; the cost of losing the race",
    "         * is a duplicated no-op, not a corrupted schema.",
    "         */",
    "        public static function avalon_hours_maybe_install(){",
    "            if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {",
    "                return;",
    "            }",
    "            if ( get_option( self::HOURS_DB_OPTION ) === self::HOURS_DB_VERSION ) {",
    "                return;",
    "            }",
    "            self::avalon_hours_install();",
    "        }",
    "",
    "        /**",
    "         * v0.0.26 Part 1. Hours configuration.",
    "         *",
    "         * Shaped after avalon_import_config(): one defined() override per",
    "         * key with its default beside it, so there is one configuration",
    "         * idiom in this plugin rather than two - s0.192.",
    "         *",
    "         * THE TWO TTLs ARE CLAMPED, NOT DEFAULTED. Google's Places terms",
    "         * allow place_id to be held indefinitely and cap every other",
    "         * field at 30 days. A constant is something somebody can set to",
    "         * 60 in wp-config.php; a clamp is not. Enforcing the cap in code",
    "         * rather than leaving it to cache eviction is the second half of",
    "         * why the durable store is a table row and not a transient -",
    "         * s0.186 - and it is the stronger position to be in if the",
    "         * licence is ever the question.",
    "         *",
    "         * resolve_ceiling, details_ceiling and timeout have NO consumer",
    "         * in Part 1. They are declared here because Part 2 reads its",
    "         * contract from this method, and a contract written twice is a",
    "         * contract that eventually disagrees with itself.",
    "         */",
    "        public function avalon_hours_config(){",
    "            $positive = defined('AVALON_HOURS_POSITIVE_TTL_DAYS')",
    "                        ? (int) AVALON_HOURS_POSITIVE_TTL_DAYS : 30;",
    "            $negative = defined('AVALON_HOURS_NEGATIVE_TTL_DAYS')",
    "                        ? (int) AVALON_HOURS_NEGATIVE_TTL_DAYS : 7;",
    "",
    "            return array(",
    "                'enabled'           => defined('AVALON_HOURS_ENABLED')",
    "                                       ? (bool) AVALON_HOURS_ENABLED   : true,",
    "                'positive_ttl_days' => max( 1, min( 30, $positive ) ),",
    "                'negative_ttl_days' => max( 1, min( 30, $negative ) ),",
    "                'resolve_ceiling'   => defined('AVALON_HOURS_RESOLVE_CEILING')",
    "                                       ? (int) AVALON_HOURS_RESOLVE_CEILING : 50,",
    "                'details_ceiling'   => defined('AVALON_HOURS_DETAILS_CEILING')",
    "                                       ? (int) AVALON_HOURS_DETAILS_CEILING : 50,",
    "                'timeout'           => defined('AVALON_HOURS_TIMEOUT')",
    "                                       ? (int) AVALON_HOURS_TIMEOUT    : 8,",
    "            );",
    "        }",
    "",
    "        public function avalon_rest_protected_slugs(){",
])


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v026.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    if not os.path.isdir(out_dir):
        os.makedirs(out_dir)

    print("build-v026  slp_avalon 0.0.25 -> 0.0.26  PART 1 of 3 (storage)")
    print("")

    # ---- inputs -----------------------------------------------------------
    blobs = {}
    for name, (md5, size) in PINS.items():
        text, got_md5, got_size = read_exact(os.path.join(src_dir, name))
        if got_md5 != md5 or got_size != size:
            sys.exit("ABORT {}: got {} / {} bytes, expected {} / {} bytes".format(
                name, got_md5, got_size, md5, size))
        print("  input OK  {:<24} {} {} bytes".format(name, got_md5, got_size))
        blobs[name] = text
    print("")

    php  = blobs['class.slp_avalon.php']
    boot = blobs['slp_avalon.php']

    # ---- patches ----------------------------------------------------------
    php = sub_once(php, CONST_ANCHOR,   CONST_BLOCK,   'class constants')
    php = sub_once(php, ACTIVATE_ANCHOR, ACTIVATE_BLOCK, 'activate() filled in')
    php = sub_once(php, HOOK_ANCHOR,    HOOK_BLOCK,    'init gate registered')
    php = sub_once(php, METHODS_ANCHOR, METHODS_BLOCK, 'hours methods')
    boot = sub_once(boot, 'Version: 0.0.25', 'Version: 0.0.26', 'version header')
    print("")

    # ---- self-checks ------------------------------------------------------
    # Qualified forms throughout. A bare count of a name that also appears in
    # a docblock is a census, and s0.185 caught that three times in one file.

    check(php.count("const HOURS_DB_VERSION = '1';") == 1,
          'schema version constant declared once')
    check(php.count("const HOURS_DB_OPTION  = 'avalon_hours_db_version';") == 1,
          'schema option constant declared once')

    check(php.count("public static function avalon_hours_install(){") == 1,
          'installer declared once')
    check(php.count("public static function avalon_hours_maybe_install(){") == 1,
          'gate declared once')
    check(php.count("public static function avalon_hours_table(){") == 1,
          'table-name helper declared once')
    check(php.count("public function avalon_hours_config(){") == 1,
          'config declared once')

    # The gate must be reachable from init AND the installer from activate.
    # Either alone is the s0.189 defect, so both are asserted as a pair.
    check(php.count("add_action('init', array(self::$instance,"
                    "'avalon_hours_maybe_install'), 1);") == 1,
          'gate registered on init at priority 1')
    # Span-qualified, not counted. "self::avalon_hours_install();" followed by
    # a closing brace occurs in BOTH activate() and the gate, so a bare count
    # of that sequence asserts nothing about either - s0.185, caught here by
    # the check itself rather than after shipping.
    a_start = php.find("        public static function activate(){")
    a_end   = php.find("        private function includes(){")
    check(a_start != -1 and a_end > a_start,
          'activate() and its following method both located')
    activate_body = php[a_start:a_end]
    check(activate_body.count("self::avalon_hours_install();") == 1,
          'activate() calls the installer exactly once, inside its own body')
    # activate() must DELEGATE, not carry its own copy of the schema. Two
    # CREATE TABLE statements that drift apart is the failure this forecloses.
    # Asserted on dbDelta( rather than on a bare word, because the surrounding
    # comment names register_activation_hook and dbDelta in prose and a bare
    # count would match the explanation instead of the code. s0.185, third
    # time in this one file - comments are part of the artefact.
    check('dbDelta( $sql )' not in activate_body,
          'activate() delegates to the installer rather than repeating the SQL')

    # dbDelta's parser constraints.
    #
    # SCOPED TO THE SQL SPAN, not the file. The docblock immediately above the
    # statement explains why CURRENT_TIMESTAMP, zero dates and prefix indexes
    # are absent, and therefore contains all three strings. Asserting their
    # absence file-wide asserts that the code is undocumented. Fourth census in
    # this file and the fourth caught by a check rather than by a reader.
    check(php.count('$sql = implode("\\n", array(') == 1,
          'SQL is assembled with explicit LF, not a CRLF literal')
    s_start = php.find('$sql = implode("\\n", array(')
    s_end   = php.find('));', s_start)
    check(s_start != -1 and s_end > s_start, 'SQL span located')
    sql = php[s_start:s_end]

    check(sql.count('"  PRIMARY KEY  (address_key),"') == 1,
          'PRIMARY KEY carries two spaces, which is what dbDelta parses on')
    check(sql.count('"  PRIMARY KEY (address_key),"') == 0,
          'the one-space form is absent from the statement')
    check(' INDEX ' not in sql and 'INDEX(' not in sql,
          'no INDEX keyword - dbDelta only understands KEY')
    check('CURRENT_TIMESTAMP' not in sql,
          'no CURRENT_TIMESTAMP default in the statement')
    check('0000-00-00' not in sql,
          'no zero date in the statement')
    upper = [t for t in ('VARCHAR', 'DATETIME', 'BIGINT', 'CHAR(', 'LONGTEXT',
                         'SMALLINT', 'TEXT ', 'NOT NULL', 'DEFAULT ')
             if t in sql]
    check(not upper,
          'every column type and attribute is lowercase, so dbDelta cannot '
          'loop on ALTER' + ('' if not upper else ': ' + ', '.join(upper)))
    check(sql.count('KEY ') == 5,
          'five keys declared: primary, sl_id, place_id and two sweeps')
    check('(64)' not in sql and '(128)' not in sql and '(191))' not in sql,
          'no index prefix lengths in the statement')
    check(sql.count('"  place_id varchar(191) null default null,"') == 1,
          'place_id is index-safe width and indexed whole')
    # Three, measured, not four. place_checked_at, fetched_at, updated_at.
    check(sql.count('datetime null default null') == 3,
          'all three timestamps are nullable with a null default')

    # Clamps, asserted as pairs: the clamp arrived and the bare default did not
    # survive beside it.
    check(php.count("'positive_ttl_days' => max( 1, min( 30, $positive ) ),") == 1,
          'positive TTL is clamped to 30, not merely defaulted')
    check(php.count("'negative_ttl_days' => max( 1, min( 30, $negative ) ),") == 1,
          'negative TTL is clamped to 30, not merely defaulted')

    check(php.count('require_once ABSPATH') == 1,
          'upgrade.php required exactly once')

    check(php.count('{') == php.count('}'),
          'php braces balance')
    check(php.count('avalon_rest_protected_slugs(){') == 1,
          'the anchor method was moved, not duplicated')

    check(boot.count('Version: 0.0.26') == 1 and boot.count('Version: 0.0.25') == 0,
          'version arrived and the old one departed')
    print("")

    # ---- outputs ----------------------------------------------------------
    for name, text in (('class.slp_avalon.php', php),
                       ('slp_avalon.php', boot)):
        md5, size, crs = write_exact(os.path.join(out_dir, name), text)
        print("  output    {:<24} {} {} bytes  CR={}".format(name, md5, size, crs))

    print("")
    print("  note      slp_avalon.js is not an input and not an output at")
    print("            Part 1. Its rev33 pin stands unchanged.")
    print("")
    print("self-check ok")
    print("done")


if __name__ == '__main__':
    main()
