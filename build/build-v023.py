#!/usr/bin/env python3
"""
build-v023.py  -  slp_avalon v0.0.22 -> v0.0.23

Refuses to run unless both inputs match their release pins byte-for-byte.
Every replacement asserts a unique anchor match before applying.
CRLF line endings are preserved throughout.

Changes
-------
class.slp_avalon.php
  1. avalon_map_location_sc_func(): read map_end_icon, google_map_style and
     zoom_level server-side from $slplus->options and echo them into the
     emitted script. Removes all three slplus.options reads, so the store
     page map no longer depends on a JS global that WP Rocket's deferred-JS
     inline wrapper turns into a function-local binding.
  2. avalon_map_location_sc_func(): explicit map controls. cameraControl
     false removes the combined pan+zoom cluster; zoomControl leaves the
     plain +/- buttons.
  3. splus_get_google_maps_url(): pin &v=quarterly so Google's weekly
     channel cannot reshape the control surface between deploys.

slp_avalon.php
  4. Version header 0.0.22 -> 0.0.23.

Usage:  python build-v023.py <src_dir> <out_dir>
"""

import hashlib
import io
import os
import sys

PINS = {
    'class.slp_avalon.php': ('9716901084fae752e831bafecd7db065', 113833),
    'slp_avalon.php':       ('68bd80e14278e547062246e6359d8fee', 1808),
}

# Project knowledge flattens the dotted filename; accept either on input.
SRC_ALIASES = {
    'class.slp_avalon.php': ('class.slp_avalon.php', 'class_slp_avalon.php'),
    'slp_avalon.php':       ('slp_avalon.php',),
}


def read_pinned(src_dir, canonical):
    for name in SRC_ALIASES[canonical]:
        path = os.path.join(src_dir, name)
        if os.path.exists(path):
            raw = io.open(path, 'rb').read()
            md5, size = hashlib.md5(raw).hexdigest(), len(raw)
            want_md5, want_size = PINS[canonical]
            if md5 != want_md5 or size != want_size:
                sys.exit(
                    f"ABORT {canonical}: got {md5} / {size} bytes, "
                    f"expected {want_md5} / {want_size} bytes"
                )
            print(f"  input OK  {canonical:24} {md5} {size} bytes")
            return raw.decode('utf-8')
    sys.exit(f"ABORT: none of {SRC_ALIASES[canonical]} found in {src_dir}")


def sub_once(text, old, new, label):
    n = text.count(old)
    if n != 1:
        sys.exit(f"ABORT {label}: anchor matched {n} times, expected exactly 1")
    print(f"  patched   {label}")
    return text.replace(old, new, 1)


def main():
    if len(sys.argv) != 3:
        sys.exit("usage: build-v023.py <src_dir> <out_dir>")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    os.makedirs(out_dir, exist_ok=True)

    print("build-v023  slp_avalon 0.0.22 -> 0.0.23")
    print("verifying inputs against release pins")

    cls = read_pinned(src_dir, 'class.slp_avalon.php')
    boot = read_pinned(src_dir, 'slp_avalon.php')

    CR = '\r\n'

    # ---- edit 1: server-side option reads ---------------------------------
    old1 = (
        '            $location = $slplus->currentLocation;' + CR +
        '            $html = "";' + CR +
        '            if ($location && ($location->latitude && $location->longitude)) {' + CR +
        '                ob_start(); ?>' + CR
    )
    new1 = (
        '            $location = $slplus->currentLocation;' + CR +
        '            $html = "";' + CR +
        '            if ($location && ($location->latitude && $location->longitude)) {' + CR +
        '                // Presentation values are read here and echoed into the script'
        ' below.' + CR +
        '                // This map deliberately does not touch the slplus JS global: WP'
        ' Rocket\'s' + CR +
        '                // "Load JavaScript deferred" wraps SLP\'s inline localisation in a'
        + CR +
        '                // DOMContentLoaded callback, which turns its `const slplus` into a'
        + CR +
        '                // function-local binding no other script can reach. Reading the'
        + CR +
        '                // same values from PHP makes this map immune to that, and to any'
        + CR +
        '                // future optimiser that moves inline scripts around.' + CR +
        '                //' + CR +
        '                // map_end_icon, not map_home_icon: on a store page the dealer is a'
        + CR +
        '                // destination, not a search origin. Using map_home_icon here would'
        + CR +
        '                // also couple this map to the locator\'s home marker.' + CR +
        '                $map_icon = isset($slplus->options[\'map_end_icon\'])'
        ' ? trim((string) $slplus->options[\'map_end_icon\']) : \'\';' + CR +
        '                $zoom = isset($slplus->options[\'zoom_level\'])'
        ' ? (int) $slplus->options[\'zoom_level\'] : 12;' + CR +
        '                if ($zoom < 1 || $zoom > 21) {' + CR +
        '                    $zoom = 12;' + CR +
        '                }' + CR +
        '                // Validate the style server-side. A malformed value previously'
        + CR +
        '                // reached JSON.parse() at runtime and took the whole map down;'
        + CR +
        '                // now it degrades to an unstyled map instead.' + CR +
        '                $map_style_json = \'\';' + CR +
        '                if (! empty($slplus->options[\'google_map_style\'])) {' + CR +
        '                    $decoded = json_decode((string) $slplus->options[\'google_map_style\'], true);' + CR +
        '                    if (is_array($decoded)) {' + CR +
        '                        $map_style_json = wp_json_encode($decoded);' + CR +
        '                    }' + CR +
        '                }' + CR +
        '                ob_start(); ?>' + CR
    )
    cls = sub_once(cls, old1, new1, 'edit 1  server-side option reads')

    # ---- edit 2: init function, no slplus, explicit controls --------------
    old2 = (
        '                        function avalon_init_location_map() {' + CR +
        '                            const location_coords = get_location_coords();' + CR +
        '                            let map_options = {' + CR +
        '                                zoom: 12,' + CR +
        '                                center: location_coords,' + CR +
        "                                gestureHandling: 'cooperative'" + CR +
        '                            }' + CR +
        '                            if (slplus.options.google_map_style) {' + CR +
        '                                jQuery.extend(map_options, {' + CR +
        '                                    styles: JSON.parse(slplus.options.google_map_style),' + CR +
        '                                });' + CR +
        '                            }' + CR +
        "                            const map = new google.maps.Map(document.getElementById('avalon_location_map'), map_options);" + CR +
        '                            const marker = new google.maps.Marker({' + CR +
        '                                position: location_coords,' + CR +
        '                                map: map,' + CR +
        '                                icon: slplus.options.map_home_icon' + CR +
        '                            });' + CR +
        '                        }' + CR
    )
    new2 = (
        '                        function avalon_init_location_map() {' + CR +
        '                            const location_coords = get_location_coords();' + CR +
        '                            let map_options = {' + CR +
        '                                zoom: <?php echo $zoom; ?>,' + CR +
        '                                center: location_coords,' + CR +
        "                                gestureHandling: 'cooperative'," + CR +
        '                                // cameraControl false removes the combined pan-arrow' + CR +
        '                                // and zoom cluster (gmp-internal-camera-control),' + CR +
        '                                // leaving the plain +/- buttons below.' + CR +
        '                                cameraControl: false,' + CR +
        '                                zoomControl: true,' + CR +
        '                                mapTypeControl: true,' + CR +
        '                                streetViewControl: true,' + CR +
        '                                fullscreenControl: true' + CR +
        '                            }' + CR +
        '<?php if ($map_style_json !== \'\') : ?>' + CR +
        '                            map_options.styles = <?php echo $map_style_json; ?>;' + CR +
        '<?php endif; ?>' + CR +
        "                            const map = new google.maps.Map(document.getElementById('avalon_location_map'), map_options);" + CR +
        '                            const marker_options = {' + CR +
        '                                position: location_coords,' + CR +
        '                                map: map' + CR +
        '                            };' + CR +
        '<?php if ($map_icon !== \'\') : ?>' + CR +
        '                            marker_options.icon = <?php echo wp_json_encode(esc_url_raw($map_icon)); ?>;' + CR +
        '<?php endif; ?>' + CR +
        '                            const marker = new google.maps.Marker(marker_options);' + CR +
        '                        }' + CR
    )
    cls = sub_once(cls, old2, new2, 'edit 2  init fn, controls, no slplus')

    # ---- edit 3: pin the Maps JS release channel --------------------------
    old3 = (
        '            $callback = "&callback=avalon_init_gmaps";' + CR +
        '            return $google_api_url . $language . $region . $server_key . $callback;' + CR
    )
    new3 = (
        '            $callback = "&callback=avalon_init_gmaps";' + CR +
        '            // Pin the Maps JS release channel. With no v= parameter Google serves' + CR +
        '            // the weekly channel, which can change the control surface and the' + CR +
        '            // internal DOM between deploys - that is how gmp-internal-camera-control' + CR +
        '            // appeared in a map nobody had edited. quarterly still receives fixes,' + CR +
        '            // on a cadence we can plan around.' + CR +
        '            $api_version = "&v=quarterly";' + CR +
        '            return $google_api_url . $language . $region . $server_key . $callback . $api_version;' + CR
    )
    cls = sub_once(cls, old3, new3, 'edit 3  pin &v=quarterly')

    # ---- edit 4: version bump --------------------------------------------
    old4 = ' * Version: 0.0.22' + CR
    new4 = ' * Version: 0.0.23' + CR
    boot = sub_once(boot, old4, new4, 'edit 4  version 0.0.22 -> 0.0.23')

    for name, text in (('class.slp_avalon.php', cls), ('slp_avalon.php', boot)):
        path = os.path.join(out_dir, name)
        io.open(path, 'w', encoding='utf-8', newline='').write(text)
        raw = io.open(path, 'rb').read()
        print(f"  output    {name:24} {hashlib.md5(raw).hexdigest()} {len(raw)} bytes"
              f"  CR={raw.count(bytes([13]))}")

    print("done")


if __name__ == '__main__':
    main()
