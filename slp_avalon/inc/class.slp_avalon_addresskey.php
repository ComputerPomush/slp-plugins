<?php
/**
 * class.slp_avalon_addresskey.php  --  GENERATED.  DO NOT HAND-EDIT.
 *
 * Produced by build/build-addresskey.py 1.0.0 from:
 *   resolve-placeids.py  md5 a29e6c7f2e5870b373ed214c306cd77a  bytes 63265
 *   Python unicodedata   16.0.0
 *
 * The PHP half of the address-key contract.  dealer_key is
 * sha1(country|state|city_key|zip|street_key) truncated to 12 hex characters,
 * and it must agree with resolve-placeids.py on every input, because a key
 * that disagrees does not throw -- it writes a row into
 * wp_avalon_dealer_places that never joins to placeids.json.
 *
 * AGREEMENT IS ASSERTED, NOT CLAIMED.  test/diff-address-key.php compares this
 * class against build/placeid/keyvectors.csv: 749 vectors, 9 columns, feeding
 * the raw fields in and checking every intermediate, not just the final key.
 * Edits to this file that are not made in the generator will be overwritten by
 * the next build and are not covered by the maps' provenance.
 *
 * NO ext-intl, NO ext-mbstring, NO ext-dom.  The NFKD decomposition, the
 * combining-mark strip and the Unicode uppercase are precomputed per codepoint
 * into $fold, so no Unicode extension is called at runtime.
 *
 * PRECONDITION, not a guarantee.  $fold covers Latin-1 Supplement through
 * Latin Extended-B, Latin Extended Additional, General Punctuation, Letterlike
 * and Number Forms, the alphabetic presentation forms and fullwidth ASCII.  A
 * codepoint outside those ranges is passed through unchanged and COUNTED;
 * unmapped() returns the tally so a drifted feed is reported rather than
 * silently keyed.  All three feeds measured 2026-09-14 contain zero non-ASCII
 * bytes in any column, so this counter reads 0 on current data.
 *
 * Tables: 51 US states, 16 CA provinces, 8 directionals, 22 suffixes,
 * 12 unit words, 1125 fold entries, 112 combining marks.
 * Fold table md5 c8b0cf09f56fc6026af9a8c9addc7a8e, unicodedata 16.0.0.
 */

if ( ! class_exists( 'SLP_Avalon_AddressKey' ) ) :

class SLP_Avalon_AddressKey {

        private static $US_STATES = array(
            "ALABAMA"=>"AL", "ALASKA"=>"AK", "ARIZONA"=>"AZ", "ARKANSAS"=>"AR", "CALIFORNIA"=>"CA",
            "COLORADO"=>"CO", "CONNECTICUT"=>"CT", "DELAWARE"=>"DE", "DISTRICT OF COLUMBIA"=>"DC",
            "FLORIDA"=>"FL", "GEORGIA"=>"GA", "HAWAII"=>"HI", "IDAHO"=>"ID", "ILLINOIS"=>"IL",
            "INDIANA"=>"IN", "IOWA"=>"IA", "KANSAS"=>"KS", "KENTUCKY"=>"KY", "LOUISIANA"=>"LA",
            "MAINE"=>"ME", "MARYLAND"=>"MD", "MASSACHUSETTS"=>"MA", "MICHIGAN"=>"MI",
            "MINNESOTA"=>"MN", "MISSISSIPPI"=>"MS", "MISSOURI"=>"MO", "MONTANA"=>"MT",
            "NEBRASKA"=>"NE", "NEVADA"=>"NV", "NEW HAMPSHIRE"=>"NH", "NEW JERSEY"=>"NJ",
            "NEW MEXICO"=>"NM", "NEW YORK"=>"NY", "NORTH CAROLINA"=>"NC", "NORTH DAKOTA"=>"ND",
            "OHIO"=>"OH", "OKLAHOMA"=>"OK", "OREGON"=>"OR", "PENNSYLVANIA"=>"PA",
            "RHODE ISLAND"=>"RI", "SOUTH CAROLINA"=>"SC", "SOUTH DAKOTA"=>"SD", "TENNESSEE"=>"TN",
            "TEXAS"=>"TX", "UTAH"=>"UT", "VERMONT"=>"VT", "VIRGINIA"=>"VA", "WASHINGTON"=>"WA",
            "WEST VIRGINIA"=>"WV", "WISCONSIN"=>"WI", "WYOMING"=>"WY",
        );

        private static $CA_PROVINCES = array(
            "ALBERTA"=>"AB", "BRITISH COLUMBIA"=>"BC", "MANITOBA"=>"MB", "NEW BRUNSWICK"=>"NB",
            "NEWFOUNDLAND AND LABRADOR"=>"NL", "NEWFOUNDLAND"=>"NL", "NORTHWEST TERRITORIES"=>"NT",
            "NOVA SCOTIA"=>"NS", "NUNAVUT"=>"NU", "ONTARIO"=>"ON", "ONT"=>"ON",
            "PRINCE EDWARD ISLAND"=>"PE", "QUEBEC"=>"QC", "QU\xC3\x83\xC2\x89BEC"=>"QC",
            "SASKATCHEWAN"=>"SK", "YUKON"=>"YT",
        );

        private static $DIRECTIONALS = array(
            "NORTH"=>"N", "SOUTH"=>"S", "EAST"=>"E", "WEST"=>"W", "NORTHEAST"=>"NE",
            "NORTHWEST"=>"NW", "SOUTHEAST"=>"SE", "SOUTHWEST"=>"SW",
        );

        private static $SUFFIXES = array(
            "STREET"=>"ST", "ROAD"=>"RD", "AVENUE"=>"AVE", "AV"=>"AVE", "DRIVE"=>"DR",
            "HIGHWAY"=>"HWY", "HIWAY"=>"HWY", "BOULEVARD"=>"BLVD", "LANE"=>"LN", "COURT"=>"CT",
            "PLACE"=>"PL", "PARKWAY"=>"PKWY", "CIRCLE"=>"CIR", "TERRACE"=>"TER", "TRAIL"=>"TRL",
            "ROUTE"=>"RTE", "TURNPIKE"=>"TPKE", "EXPRESSWAY"=>"EXPY", "SQUARE"=>"SQ", "POINT"=>"PT",
            "CROSSING"=>"XING", "EXTENSION"=>"EXT",
        );

        private static $UNIT_WORDS = array(
            "STE", "SUITE", "UNIT", "APT", "APARTMENT", "BLDG", "BUILDING", "RM", "ROOM", "FL",
            "FLOOR", "#",
        );

        private static $US_CODES = array(
            "AK"=>"1", "AL"=>"1", "AR"=>"1", "AZ"=>"1", "CA"=>"1", "CO"=>"1", "CT"=>"1", "DC"=>"1",
            "DE"=>"1", "FL"=>"1", "GA"=>"1", "HI"=>"1", "IA"=>"1", "ID"=>"1", "IL"=>"1", "IN"=>"1",
            "KS"=>"1", "KY"=>"1", "LA"=>"1", "MA"=>"1", "MD"=>"1", "ME"=>"1", "MI"=>"1", "MN"=>"1",
            "MO"=>"1", "MS"=>"1", "MT"=>"1", "NC"=>"1", "ND"=>"1", "NE"=>"1", "NH"=>"1", "NJ"=>"1",
            "NM"=>"1", "NV"=>"1", "NY"=>"1", "OH"=>"1", "OK"=>"1", "OR"=>"1", "PA"=>"1", "RI"=>"1",
            "SC"=>"1", "SD"=>"1", "TN"=>"1", "TX"=>"1", "UT"=>"1", "VA"=>"1", "VT"=>"1", "WA"=>"1",
            "WI"=>"1", "WV"=>"1", "WY"=>"1",
        );

        private static $CA_CODES = array(
            "AB"=>"1", "BC"=>"1", "MB"=>"1", "NB"=>"1", "NL"=>"1", "NS"=>"1", "NT"=>"1", "NU"=>"1",
            "ON"=>"1", "PE"=>"1", "QC"=>"1", "SK"=>"1", "YT"=>"1",
        );

        private static $fold = array(
            0x001C=>" ", 0x001D=>" ", 0x001E=>" ", 0x001F=>" ", 0x0085=>" ", 0x00A0=>" ",
            0x00A1=>"\xC2\xA1", 0x00A2=>"\xC2\xA2", 0x00A3=>"\xC2\xA3", 0x00A4=>"\xC2\xA4",
            0x00A5=>"\xC2\xA5", 0x00A6=>"\xC2\xA6", 0x00A7=>"\xC2\xA7", 0x00A8=>" ",
            0x00A9=>"\xC2\xA9", 0x00AA=>"A", 0x00AB=>"\xC2\xAB", 0x00AC=>"\xC2\xAC",
            0x00AD=>"\xC2\xAD", 0x00AE=>"\xC2\xAE", 0x00AF=>" ", 0x00B0=>"\xC2\xB0",
            0x00B1=>"\xC2\xB1", 0x00B2=>"2", 0x00B3=>"3", 0x00B4=>" ", 0x00B5=>"\xCE\x9C",
            0x00B6=>"\xC2\xB6", 0x00B7=>"\xC2\xB7", 0x00B8=>" ", 0x00B9=>"1", 0x00BA=>"O",
            0x00BB=>"\xC2\xBB", 0x00BC=>"1\xE2\x81\x844", 0x00BD=>"1\xE2\x81\x842",
            0x00BE=>"3\xE2\x81\x844", 0x00BF=>"\xC2\xBF", 0x00C0=>"A", 0x00C1=>"A", 0x00C2=>"A",
            0x00C3=>"A", 0x00C4=>"A", 0x00C5=>"A", 0x00C6=>"\xC3\x86", 0x00C7=>"C", 0x00C8=>"E",
            0x00C9=>"E", 0x00CA=>"E", 0x00CB=>"E", 0x00CC=>"I", 0x00CD=>"I", 0x00CE=>"I",
            0x00CF=>"I", 0x00D0=>"\xC3\x90", 0x00D1=>"N", 0x00D2=>"O", 0x00D3=>"O", 0x00D4=>"O",
            0x00D5=>"O", 0x00D6=>"O", 0x00D7=>"\xC3\x97", 0x00D8=>"\xC3\x98", 0x00D9=>"U",
            0x00DA=>"U", 0x00DB=>"U", 0x00DC=>"U", 0x00DD=>"Y", 0x00DE=>"\xC3\x9E", 0x00DF=>"SS",
            0x00E0=>"A", 0x00E1=>"A", 0x00E2=>"A", 0x00E3=>"A", 0x00E4=>"A", 0x00E5=>"A",
            0x00E6=>"\xC3\x86", 0x00E7=>"C", 0x00E8=>"E", 0x00E9=>"E", 0x00EA=>"E", 0x00EB=>"E",
            0x00EC=>"I", 0x00ED=>"I", 0x00EE=>"I", 0x00EF=>"I", 0x00F0=>"\xC3\x90", 0x00F1=>"N",
            0x00F2=>"O", 0x00F3=>"O", 0x00F4=>"O", 0x00F5=>"O", 0x00F6=>"O", 0x00F7=>"\xC3\xB7",
            0x00F8=>"\xC3\x98", 0x00F9=>"U", 0x00FA=>"U", 0x00FB=>"U", 0x00FC=>"U", 0x00FD=>"Y",
            0x00FE=>"\xC3\x9E", 0x00FF=>"Y", 0x0100=>"A", 0x0101=>"A", 0x0102=>"A", 0x0103=>"A",
            0x0104=>"A", 0x0105=>"A", 0x0106=>"C", 0x0107=>"C", 0x0108=>"C", 0x0109=>"C",
            0x010A=>"C", 0x010B=>"C", 0x010C=>"C", 0x010D=>"C", 0x010E=>"D", 0x010F=>"D",
            0x0110=>"\xC4\x90", 0x0111=>"\xC4\x90", 0x0112=>"E", 0x0113=>"E", 0x0114=>"E",
            0x0115=>"E", 0x0116=>"E", 0x0117=>"E", 0x0118=>"E", 0x0119=>"E", 0x011A=>"E",
            0x011B=>"E", 0x011C=>"G", 0x011D=>"G", 0x011E=>"G", 0x011F=>"G", 0x0120=>"G",
            0x0121=>"G", 0x0122=>"G", 0x0123=>"G", 0x0124=>"H", 0x0125=>"H", 0x0126=>"\xC4\xA6",
            0x0127=>"\xC4\xA6", 0x0128=>"I", 0x0129=>"I", 0x012A=>"I", 0x012B=>"I", 0x012C=>"I",
            0x012D=>"I", 0x012E=>"I", 0x012F=>"I", 0x0130=>"I", 0x0131=>"I", 0x0132=>"IJ",
            0x0133=>"IJ", 0x0134=>"J", 0x0135=>"J", 0x0136=>"K", 0x0137=>"K", 0x0138=>"\xC4\xB8",
            0x0139=>"L", 0x013A=>"L", 0x013B=>"L", 0x013C=>"L", 0x013D=>"L", 0x013E=>"L",
            0x013F=>"L\xC2\xB7", 0x0140=>"L\xC2\xB7", 0x0141=>"\xC5\x81", 0x0142=>"\xC5\x81",
            0x0143=>"N", 0x0144=>"N", 0x0145=>"N", 0x0146=>"N", 0x0147=>"N", 0x0148=>"N",
            0x0149=>"\xCA\xBCN", 0x014A=>"\xC5\x8A", 0x014B=>"\xC5\x8A", 0x014C=>"O", 0x014D=>"O",
            0x014E=>"O", 0x014F=>"O", 0x0150=>"O", 0x0151=>"O", 0x0152=>"\xC5\x92",
            0x0153=>"\xC5\x92", 0x0154=>"R", 0x0155=>"R", 0x0156=>"R", 0x0157=>"R", 0x0158=>"R",
            0x0159=>"R", 0x015A=>"S", 0x015B=>"S", 0x015C=>"S", 0x015D=>"S", 0x015E=>"S",
            0x015F=>"S", 0x0160=>"S", 0x0161=>"S", 0x0162=>"T", 0x0163=>"T", 0x0164=>"T",
            0x0165=>"T", 0x0166=>"\xC5\xA6", 0x0167=>"\xC5\xA6", 0x0168=>"U", 0x0169=>"U",
            0x016A=>"U", 0x016B=>"U", 0x016C=>"U", 0x016D=>"U", 0x016E=>"U", 0x016F=>"U",
            0x0170=>"U", 0x0171=>"U", 0x0172=>"U", 0x0173=>"U", 0x0174=>"W", 0x0175=>"W",
            0x0176=>"Y", 0x0177=>"Y", 0x0178=>"Y", 0x0179=>"Z", 0x017A=>"Z", 0x017B=>"Z",
            0x017C=>"Z", 0x017D=>"Z", 0x017E=>"Z", 0x017F=>"S", 0x0180=>"\xC9\x83",
            0x0181=>"\xC6\x81", 0x0182=>"\xC6\x82", 0x0183=>"\xC6\x82", 0x0184=>"\xC6\x84",
            0x0185=>"\xC6\x84", 0x0186=>"\xC6\x86", 0x0187=>"\xC6\x87", 0x0188=>"\xC6\x87",
            0x0189=>"\xC6\x89", 0x018A=>"\xC6\x8A", 0x018B=>"\xC6\x8B", 0x018C=>"\xC6\x8B",
            0x018D=>"\xC6\x8D", 0x018E=>"\xC6\x8E", 0x018F=>"\xC6\x8F", 0x0190=>"\xC6\x90",
            0x0191=>"\xC6\x91", 0x0192=>"\xC6\x91", 0x0193=>"\xC6\x93", 0x0194=>"\xC6\x94",
            0x0195=>"\xC7\xB6", 0x0196=>"\xC6\x96", 0x0197=>"\xC6\x97", 0x0198=>"\xC6\x98",
            0x0199=>"\xC6\x98", 0x019A=>"\xC8\xBD", 0x019B=>"\xEA\x9F\x9C", 0x019C=>"\xC6\x9C",
            0x019D=>"\xC6\x9D", 0x019E=>"\xC8\xA0", 0x019F=>"\xC6\x9F", 0x01A0=>"O", 0x01A1=>"O",
            0x01A2=>"\xC6\xA2", 0x01A3=>"\xC6\xA2", 0x01A4=>"\xC6\xA4", 0x01A5=>"\xC6\xA4",
            0x01A6=>"\xC6\xA6", 0x01A7=>"\xC6\xA7", 0x01A8=>"\xC6\xA7", 0x01A9=>"\xC6\xA9",
            0x01AA=>"\xC6\xAA", 0x01AB=>"\xC6\xAB", 0x01AC=>"\xC6\xAC", 0x01AD=>"\xC6\xAC",
            0x01AE=>"\xC6\xAE", 0x01AF=>"U", 0x01B0=>"U", 0x01B1=>"\xC6\xB1", 0x01B2=>"\xC6\xB2",
            0x01B3=>"\xC6\xB3", 0x01B4=>"\xC6\xB3", 0x01B5=>"\xC6\xB5", 0x01B6=>"\xC6\xB5",
            0x01B7=>"\xC6\xB7", 0x01B8=>"\xC6\xB8", 0x01B9=>"\xC6\xB8", 0x01BA=>"\xC6\xBA",
            0x01BB=>"\xC6\xBB", 0x01BC=>"\xC6\xBC", 0x01BD=>"\xC6\xBC", 0x01BE=>"\xC6\xBE",
            0x01BF=>"\xC7\xB7", 0x01C0=>"\xC7\x80", 0x01C1=>"\xC7\x81", 0x01C2=>"\xC7\x82",
            0x01C3=>"\xC7\x83", 0x01C4=>"DZ", 0x01C5=>"DZ", 0x01C6=>"DZ", 0x01C7=>"LJ",
            0x01C8=>"LJ", 0x01C9=>"LJ", 0x01CA=>"NJ", 0x01CB=>"NJ", 0x01CC=>"NJ", 0x01CD=>"A",
            0x01CE=>"A", 0x01CF=>"I", 0x01D0=>"I", 0x01D1=>"O", 0x01D2=>"O", 0x01D3=>"U",
            0x01D4=>"U", 0x01D5=>"U", 0x01D6=>"U", 0x01D7=>"U", 0x01D8=>"U", 0x01D9=>"U",
            0x01DA=>"U", 0x01DB=>"U", 0x01DC=>"U", 0x01DD=>"\xC6\x8E", 0x01DE=>"A", 0x01DF=>"A",
            0x01E0=>"A", 0x01E1=>"A", 0x01E2=>"\xC3\x86", 0x01E3=>"\xC3\x86", 0x01E4=>"\xC7\xA4",
            0x01E5=>"\xC7\xA4", 0x01E6=>"G", 0x01E7=>"G", 0x01E8=>"K", 0x01E9=>"K", 0x01EA=>"O",
            0x01EB=>"O", 0x01EC=>"O", 0x01ED=>"O", 0x01EE=>"\xC6\xB7", 0x01EF=>"\xC6\xB7",
            0x01F0=>"J", 0x01F1=>"DZ", 0x01F2=>"DZ", 0x01F3=>"DZ", 0x01F4=>"G", 0x01F5=>"G",
            0x01F6=>"\xC7\xB6", 0x01F7=>"\xC7\xB7", 0x01F8=>"N", 0x01F9=>"N", 0x01FA=>"A",
            0x01FB=>"A", 0x01FC=>"\xC3\x86", 0x01FD=>"\xC3\x86", 0x01FE=>"\xC3\x98",
            0x01FF=>"\xC3\x98", 0x0200=>"A", 0x0201=>"A", 0x0202=>"A", 0x0203=>"A", 0x0204=>"E",
            0x0205=>"E", 0x0206=>"E", 0x0207=>"E", 0x0208=>"I", 0x0209=>"I", 0x020A=>"I",
            0x020B=>"I", 0x020C=>"O", 0x020D=>"O", 0x020E=>"O", 0x020F=>"O", 0x0210=>"R",
            0x0211=>"R", 0x0212=>"R", 0x0213=>"R", 0x0214=>"U", 0x0215=>"U", 0x0216=>"U",
            0x0217=>"U", 0x0218=>"S", 0x0219=>"S", 0x021A=>"T", 0x021B=>"T", 0x021C=>"\xC8\x9C",
            0x021D=>"\xC8\x9C", 0x021E=>"H", 0x021F=>"H", 0x0220=>"\xC8\xA0", 0x0221=>"\xC8\xA1",
            0x0222=>"\xC8\xA2", 0x0223=>"\xC8\xA2", 0x0224=>"\xC8\xA4", 0x0225=>"\xC8\xA4",
            0x0226=>"A", 0x0227=>"A", 0x0228=>"E", 0x0229=>"E", 0x022A=>"O", 0x022B=>"O",
            0x022C=>"O", 0x022D=>"O", 0x022E=>"O", 0x022F=>"O", 0x0230=>"O", 0x0231=>"O",
            0x0232=>"Y", 0x0233=>"Y", 0x0234=>"\xC8\xB4", 0x0235=>"\xC8\xB5", 0x0236=>"\xC8\xB6",
            0x0237=>"\xC8\xB7", 0x0238=>"\xC8\xB8", 0x0239=>"\xC8\xB9", 0x023A=>"\xC8\xBA",
            0x023B=>"\xC8\xBB", 0x023C=>"\xC8\xBB", 0x023D=>"\xC8\xBD", 0x023E=>"\xC8\xBE",
            0x023F=>"\xE2\xB1\xBE", 0x0240=>"\xE2\xB1\xBF", 0x0241=>"\xC9\x81", 0x0242=>"\xC9\x81",
            0x0243=>"\xC9\x83", 0x0244=>"\xC9\x84", 0x0245=>"\xC9\x85", 0x0246=>"\xC9\x86",
            0x0247=>"\xC9\x86", 0x0248=>"\xC9\x88", 0x0249=>"\xC9\x88", 0x024A=>"\xC9\x8A",
            0x024B=>"\xC9\x8A", 0x024C=>"\xC9\x8C", 0x024D=>"\xC9\x8C", 0x024E=>"\xC9\x8E",
            0x024F=>"\xC9\x8E", 0x034F=>"\xCD\x8F", 0x1680=>" ", 0x1E00=>"A", 0x1E01=>"A",
            0x1E02=>"B", 0x1E03=>"B", 0x1E04=>"B", 0x1E05=>"B", 0x1E06=>"B", 0x1E07=>"B",
            0x1E08=>"C", 0x1E09=>"C", 0x1E0A=>"D", 0x1E0B=>"D", 0x1E0C=>"D", 0x1E0D=>"D",
            0x1E0E=>"D", 0x1E0F=>"D", 0x1E10=>"D", 0x1E11=>"D", 0x1E12=>"D", 0x1E13=>"D",
            0x1E14=>"E", 0x1E15=>"E", 0x1E16=>"E", 0x1E17=>"E", 0x1E18=>"E", 0x1E19=>"E",
            0x1E1A=>"E", 0x1E1B=>"E", 0x1E1C=>"E", 0x1E1D=>"E", 0x1E1E=>"F", 0x1E1F=>"F",
            0x1E20=>"G", 0x1E21=>"G", 0x1E22=>"H", 0x1E23=>"H", 0x1E24=>"H", 0x1E25=>"H",
            0x1E26=>"H", 0x1E27=>"H", 0x1E28=>"H", 0x1E29=>"H", 0x1E2A=>"H", 0x1E2B=>"H",
            0x1E2C=>"I", 0x1E2D=>"I", 0x1E2E=>"I", 0x1E2F=>"I", 0x1E30=>"K", 0x1E31=>"K",
            0x1E32=>"K", 0x1E33=>"K", 0x1E34=>"K", 0x1E35=>"K", 0x1E36=>"L", 0x1E37=>"L",
            0x1E38=>"L", 0x1E39=>"L", 0x1E3A=>"L", 0x1E3B=>"L", 0x1E3C=>"L", 0x1E3D=>"L",
            0x1E3E=>"M", 0x1E3F=>"M", 0x1E40=>"M", 0x1E41=>"M", 0x1E42=>"M", 0x1E43=>"M",
            0x1E44=>"N", 0x1E45=>"N", 0x1E46=>"N", 0x1E47=>"N", 0x1E48=>"N", 0x1E49=>"N",
            0x1E4A=>"N", 0x1E4B=>"N", 0x1E4C=>"O", 0x1E4D=>"O", 0x1E4E=>"O", 0x1E4F=>"O",
            0x1E50=>"O", 0x1E51=>"O", 0x1E52=>"O", 0x1E53=>"O", 0x1E54=>"P", 0x1E55=>"P",
            0x1E56=>"P", 0x1E57=>"P", 0x1E58=>"R", 0x1E59=>"R", 0x1E5A=>"R", 0x1E5B=>"R",
            0x1E5C=>"R", 0x1E5D=>"R", 0x1E5E=>"R", 0x1E5F=>"R", 0x1E60=>"S", 0x1E61=>"S",
            0x1E62=>"S", 0x1E63=>"S", 0x1E64=>"S", 0x1E65=>"S", 0x1E66=>"S", 0x1E67=>"S",
            0x1E68=>"S", 0x1E69=>"S", 0x1E6A=>"T", 0x1E6B=>"T", 0x1E6C=>"T", 0x1E6D=>"T",
            0x1E6E=>"T", 0x1E6F=>"T", 0x1E70=>"T", 0x1E71=>"T", 0x1E72=>"U", 0x1E73=>"U",
            0x1E74=>"U", 0x1E75=>"U", 0x1E76=>"U", 0x1E77=>"U", 0x1E78=>"U", 0x1E79=>"U",
            0x1E7A=>"U", 0x1E7B=>"U", 0x1E7C=>"V", 0x1E7D=>"V", 0x1E7E=>"V", 0x1E7F=>"V",
            0x1E80=>"W", 0x1E81=>"W", 0x1E82=>"W", 0x1E83=>"W", 0x1E84=>"W", 0x1E85=>"W",
            0x1E86=>"W", 0x1E87=>"W", 0x1E88=>"W", 0x1E89=>"W", 0x1E8A=>"X", 0x1E8B=>"X",
            0x1E8C=>"X", 0x1E8D=>"X", 0x1E8E=>"Y", 0x1E8F=>"Y", 0x1E90=>"Z", 0x1E91=>"Z",
            0x1E92=>"Z", 0x1E93=>"Z", 0x1E94=>"Z", 0x1E95=>"Z", 0x1E96=>"H", 0x1E97=>"T",
            0x1E98=>"W", 0x1E99=>"Y", 0x1E9A=>"A\xCA\xBE", 0x1E9B=>"S", 0x1E9C=>"\xE1\xBA\x9C",
            0x1E9D=>"\xE1\xBA\x9D", 0x1E9E=>"\xE1\xBA\x9E", 0x1E9F=>"\xE1\xBA\x9F", 0x1EA0=>"A",
            0x1EA1=>"A", 0x1EA2=>"A", 0x1EA3=>"A", 0x1EA4=>"A", 0x1EA5=>"A", 0x1EA6=>"A",
            0x1EA7=>"A", 0x1EA8=>"A", 0x1EA9=>"A", 0x1EAA=>"A", 0x1EAB=>"A", 0x1EAC=>"A",
            0x1EAD=>"A", 0x1EAE=>"A", 0x1EAF=>"A", 0x1EB0=>"A", 0x1EB1=>"A", 0x1EB2=>"A",
            0x1EB3=>"A", 0x1EB4=>"A", 0x1EB5=>"A", 0x1EB6=>"A", 0x1EB7=>"A", 0x1EB8=>"E",
            0x1EB9=>"E", 0x1EBA=>"E", 0x1EBB=>"E", 0x1EBC=>"E", 0x1EBD=>"E", 0x1EBE=>"E",
            0x1EBF=>"E", 0x1EC0=>"E", 0x1EC1=>"E", 0x1EC2=>"E", 0x1EC3=>"E", 0x1EC4=>"E",
            0x1EC5=>"E", 0x1EC6=>"E", 0x1EC7=>"E", 0x1EC8=>"I", 0x1EC9=>"I", 0x1ECA=>"I",
            0x1ECB=>"I", 0x1ECC=>"O", 0x1ECD=>"O", 0x1ECE=>"O", 0x1ECF=>"O", 0x1ED0=>"O",
            0x1ED1=>"O", 0x1ED2=>"O", 0x1ED3=>"O", 0x1ED4=>"O", 0x1ED5=>"O", 0x1ED6=>"O",
            0x1ED7=>"O", 0x1ED8=>"O", 0x1ED9=>"O", 0x1EDA=>"O", 0x1EDB=>"O", 0x1EDC=>"O",
            0x1EDD=>"O", 0x1EDE=>"O", 0x1EDF=>"O", 0x1EE0=>"O", 0x1EE1=>"O", 0x1EE2=>"O",
            0x1EE3=>"O", 0x1EE4=>"U", 0x1EE5=>"U", 0x1EE6=>"U", 0x1EE7=>"U", 0x1EE8=>"U",
            0x1EE9=>"U", 0x1EEA=>"U", 0x1EEB=>"U", 0x1EEC=>"U", 0x1EED=>"U", 0x1EEE=>"U",
            0x1EEF=>"U", 0x1EF0=>"U", 0x1EF1=>"U", 0x1EF2=>"Y", 0x1EF3=>"Y", 0x1EF4=>"Y",
            0x1EF5=>"Y", 0x1EF6=>"Y", 0x1EF7=>"Y", 0x1EF8=>"Y", 0x1EF9=>"Y", 0x1EFA=>"\xE1\xBB\xBA",
            0x1EFB=>"\xE1\xBB\xBA", 0x1EFC=>"\xE1\xBB\xBC", 0x1EFD=>"\xE1\xBB\xBC",
            0x1EFE=>"\xE1\xBB\xBE", 0x1EFF=>"\xE1\xBB\xBE", 0x2000=>" ", 0x2001=>" ", 0x2002=>" ",
            0x2003=>" ", 0x2004=>" ", 0x2005=>" ", 0x2006=>" ", 0x2007=>" ", 0x2008=>" ",
            0x2009=>" ", 0x200A=>" ", 0x200B=>"\xE2\x80\x8B", 0x200C=>"\xE2\x80\x8C",
            0x200D=>"\xE2\x80\x8D", 0x200E=>"\xE2\x80\x8E", 0x200F=>"\xE2\x80\x8F",
            0x2010=>"\xE2\x80\x90", 0x2011=>"\xE2\x80\x90", 0x2012=>"\xE2\x80\x92",
            0x2013=>"\xE2\x80\x93", 0x2014=>"\xE2\x80\x94", 0x2015=>"\xE2\x80\x95",
            0x2016=>"\xE2\x80\x96", 0x2017=>" ", 0x2018=>"\xE2\x80\x98", 0x2019=>"\xE2\x80\x99",
            0x201A=>"\xE2\x80\x9A", 0x201B=>"\xE2\x80\x9B", 0x201C=>"\xE2\x80\x9C",
            0x201D=>"\xE2\x80\x9D", 0x201E=>"\xE2\x80\x9E", 0x201F=>"\xE2\x80\x9F",
            0x2020=>"\xE2\x80\xA0", 0x2021=>"\xE2\x80\xA1", 0x2022=>"\xE2\x80\xA2",
            0x2023=>"\xE2\x80\xA3", 0x2024=>".", 0x2025=>"..", 0x2026=>"...",
            0x2027=>"\xE2\x80\xA7", 0x2028=>" ", 0x2029=>" ", 0x202A=>"\xE2\x80\xAA",
            0x202B=>"\xE2\x80\xAB", 0x202C=>"\xE2\x80\xAC", 0x202D=>"\xE2\x80\xAD",
            0x202E=>"\xE2\x80\xAE", 0x202F=>" ", 0x2030=>"\xE2\x80\xB0", 0x2031=>"\xE2\x80\xB1",
            0x2032=>"\xE2\x80\xB2", 0x2033=>"\xE2\x80\xB2\xE2\x80\xB2",
            0x2034=>"\xE2\x80\xB2\xE2\x80\xB2\xE2\x80\xB2", 0x2035=>"\xE2\x80\xB5",
            0x2036=>"\xE2\x80\xB5\xE2\x80\xB5", 0x2037=>"\xE2\x80\xB5\xE2\x80\xB5\xE2\x80\xB5",
            0x2038=>"\xE2\x80\xB8", 0x2039=>"\xE2\x80\xB9", 0x203A=>"\xE2\x80\xBA",
            0x203B=>"\xE2\x80\xBB", 0x203C=>"!!", 0x203D=>"\xE2\x80\xBD", 0x203E=>" ",
            0x203F=>"\xE2\x80\xBF", 0x2040=>"\xE2\x81\x80", 0x2041=>"\xE2\x81\x81",
            0x2042=>"\xE2\x81\x82", 0x2043=>"\xE2\x81\x83", 0x2044=>"\xE2\x81\x84",
            0x2045=>"\xE2\x81\x85", 0x2046=>"\xE2\x81\x86", 0x2047=>"??", 0x2048=>"?!",
            0x2049=>"!?", 0x204A=>"\xE2\x81\x8A", 0x204B=>"\xE2\x81\x8B", 0x204C=>"\xE2\x81\x8C",
            0x204D=>"\xE2\x81\x8D", 0x204E=>"\xE2\x81\x8E", 0x204F=>"\xE2\x81\x8F",
            0x2050=>"\xE2\x81\x90", 0x2051=>"\xE2\x81\x91", 0x2052=>"\xE2\x81\x92",
            0x2053=>"\xE2\x81\x93", 0x2054=>"\xE2\x81\x94", 0x2055=>"\xE2\x81\x95",
            0x2056=>"\xE2\x81\x96", 0x2057=>"\xE2\x80\xB2\xE2\x80\xB2\xE2\x80\xB2\xE2\x80\xB2",
            0x2058=>"\xE2\x81\x98", 0x2059=>"\xE2\x81\x99", 0x205A=>"\xE2\x81\x9A",
            0x205B=>"\xE2\x81\x9B", 0x205C=>"\xE2\x81\x9C", 0x205D=>"\xE2\x81\x9D",
            0x205E=>"\xE2\x81\x9E", 0x205F=>" ", 0x2060=>"\xE2\x81\xA0", 0x2061=>"\xE2\x81\xA1",
            0x2062=>"\xE2\x81\xA2", 0x2063=>"\xE2\x81\xA3", 0x2064=>"\xE2\x81\xA4",
            0x2065=>"\xE2\x81\xA5", 0x2066=>"\xE2\x81\xA6", 0x2067=>"\xE2\x81\xA7",
            0x2068=>"\xE2\x81\xA8", 0x2069=>"\xE2\x81\xA9", 0x206A=>"\xE2\x81\xAA",
            0x206B=>"\xE2\x81\xAB", 0x206C=>"\xE2\x81\xAC", 0x206D=>"\xE2\x81\xAD",
            0x206E=>"\xE2\x81\xAE", 0x206F=>"\xE2\x81\xAF", 0x2100=>"A/C", 0x2101=>"A/S",
            0x2102=>"C", 0x2103=>"\xC2\xB0C", 0x2104=>"\xE2\x84\x84", 0x2105=>"C/O", 0x2106=>"C/U",
            0x2107=>"\xC6\x90", 0x2108=>"\xE2\x84\x88", 0x2109=>"\xC2\xB0F", 0x210A=>"G",
            0x210B=>"H", 0x210C=>"H", 0x210D=>"H", 0x210E=>"H", 0x210F=>"\xC4\xA6", 0x2110=>"I",
            0x2111=>"I", 0x2112=>"L", 0x2113=>"L", 0x2114=>"\xE2\x84\x94", 0x2115=>"N",
            0x2116=>"NO", 0x2117=>"\xE2\x84\x97", 0x2118=>"\xE2\x84\x98", 0x2119=>"P", 0x211A=>"Q",
            0x211B=>"R", 0x211C=>"R", 0x211D=>"R", 0x211E=>"\xE2\x84\x9E", 0x211F=>"\xE2\x84\x9F",
            0x2120=>"SM", 0x2121=>"TEL", 0x2122=>"TM", 0x2123=>"\xE2\x84\xA3", 0x2124=>"Z",
            0x2125=>"\xE2\x84\xA5", 0x2126=>"\xCE\xA9", 0x2127=>"\xE2\x84\xA7", 0x2128=>"Z",
            0x2129=>"\xE2\x84\xA9", 0x212A=>"K", 0x212B=>"A", 0x212C=>"B", 0x212D=>"C",
            0x212E=>"\xE2\x84\xAE", 0x212F=>"E", 0x2130=>"E", 0x2131=>"F", 0x2132=>"\xE2\x84\xB2",
            0x2133=>"M", 0x2134=>"O", 0x2135=>"\xD7\x90", 0x2136=>"\xD7\x91", 0x2137=>"\xD7\x92",
            0x2138=>"\xD7\x93", 0x2139=>"I", 0x213A=>"\xE2\x84\xBA", 0x213B=>"FAX",
            0x213C=>"\xCE\xA0", 0x213D=>"\xCE\x93", 0x213E=>"\xCE\x93", 0x213F=>"\xCE\xA0",
            0x2140=>"\xE2\x88\x91", 0x2141=>"\xE2\x85\x81", 0x2142=>"\xE2\x85\x82",
            0x2143=>"\xE2\x85\x83", 0x2144=>"\xE2\x85\x84", 0x2145=>"D", 0x2146=>"D", 0x2147=>"E",
            0x2148=>"I", 0x2149=>"J", 0x214A=>"\xE2\x85\x8A", 0x214B=>"\xE2\x85\x8B",
            0x214C=>"\xE2\x85\x8C", 0x214D=>"\xE2\x85\x8D", 0x214E=>"\xE2\x84\xB2",
            0x214F=>"\xE2\x85\x8F", 0x2150=>"1\xE2\x81\x847", 0x2151=>"1\xE2\x81\x849",
            0x2152=>"1\xE2\x81\x8410", 0x2153=>"1\xE2\x81\x843", 0x2154=>"2\xE2\x81\x843",
            0x2155=>"1\xE2\x81\x845", 0x2156=>"2\xE2\x81\x845", 0x2157=>"3\xE2\x81\x845",
            0x2158=>"4\xE2\x81\x845", 0x2159=>"1\xE2\x81\x846", 0x215A=>"5\xE2\x81\x846",
            0x215B=>"1\xE2\x81\x848", 0x215C=>"3\xE2\x81\x848", 0x215D=>"5\xE2\x81\x848",
            0x215E=>"7\xE2\x81\x848", 0x215F=>"1\xE2\x81\x84", 0x2160=>"I", 0x2161=>"II",
            0x2162=>"III", 0x2163=>"IV", 0x2164=>"V", 0x2165=>"VI", 0x2166=>"VII", 0x2167=>"VIII",
            0x2168=>"IX", 0x2169=>"X", 0x216A=>"XI", 0x216B=>"XII", 0x216C=>"L", 0x216D=>"C",
            0x216E=>"D", 0x216F=>"M", 0x2170=>"I", 0x2171=>"II", 0x2172=>"III", 0x2173=>"IV",
            0x2174=>"V", 0x2175=>"VI", 0x2176=>"VII", 0x2177=>"VIII", 0x2178=>"IX", 0x2179=>"X",
            0x217A=>"XI", 0x217B=>"XII", 0x217C=>"L", 0x217D=>"C", 0x217E=>"D", 0x217F=>"M",
            0x2180=>"\xE2\x86\x80", 0x2181=>"\xE2\x86\x81", 0x2182=>"\xE2\x86\x82",
            0x2183=>"\xE2\x86\x83", 0x2184=>"\xE2\x86\x83", 0x2185=>"\xE2\x86\x85",
            0x2186=>"\xE2\x86\x86", 0x2187=>"\xE2\x86\x87", 0x2188=>"\xE2\x86\x88",
            0x2189=>"0\xE2\x81\x843", 0x218A=>"\xE2\x86\x8A", 0x218B=>"\xE2\x86\x8B",
            0x218C=>"\xE2\x86\x8C", 0x218D=>"\xE2\x86\x8D", 0x218E=>"\xE2\x86\x8E",
            0x218F=>"\xE2\x86\x8F", 0x3000=>" ", 0xFB00=>"FF", 0xFB01=>"FI", 0xFB02=>"FL",
            0xFB03=>"FFI", 0xFB04=>"FFL", 0xFB05=>"ST", 0xFB06=>"ST", 0xFB07=>"\xEF\xAC\x87",
            0xFB08=>"\xEF\xAC\x88", 0xFB09=>"\xEF\xAC\x89", 0xFB0A=>"\xEF\xAC\x8A",
            0xFB0B=>"\xEF\xAC\x8B", 0xFB0C=>"\xEF\xAC\x8C", 0xFB0D=>"\xEF\xAC\x8D",
            0xFB0E=>"\xEF\xAC\x8E", 0xFB0F=>"\xEF\xAC\x8F", 0xFB10=>"\xEF\xAC\x90",
            0xFB11=>"\xEF\xAC\x91", 0xFB12=>"\xEF\xAC\x92", 0xFB13=>"\xD5\x84\xD5\x86",
            0xFB14=>"\xD5\x84\xD4\xB5", 0xFB15=>"\xD5\x84\xD4\xBB", 0xFB16=>"\xD5\x8E\xD5\x86",
            0xFB17=>"\xD5\x84\xD4\xBD", 0xFB18=>"\xEF\xAC\x98", 0xFB19=>"\xEF\xAC\x99",
            0xFB1A=>"\xEF\xAC\x9A", 0xFB1B=>"\xEF\xAC\x9B", 0xFB1C=>"\xEF\xAC\x9C",
            0xFB1D=>"\xD7\x99", 0xFB1F=>"\xD7\xB2", 0xFB20=>"\xD7\xA2", 0xFB21=>"\xD7\x90",
            0xFB22=>"\xD7\x93", 0xFB23=>"\xD7\x94", 0xFB24=>"\xD7\x9B", 0xFB25=>"\xD7\x9C",
            0xFB26=>"\xD7\x9D", 0xFB27=>"\xD7\xA8", 0xFB28=>"\xD7\xAA", 0xFB29=>"+",
            0xFB2A=>"\xD7\xA9", 0xFB2B=>"\xD7\xA9", 0xFB2C=>"\xD7\xA9", 0xFB2D=>"\xD7\xA9",
            0xFB2E=>"\xD7\x90", 0xFB2F=>"\xD7\x90", 0xFB30=>"\xD7\x90", 0xFB31=>"\xD7\x91",
            0xFB32=>"\xD7\x92", 0xFB33=>"\xD7\x93", 0xFB34=>"\xD7\x94", 0xFB35=>"\xD7\x95",
            0xFB36=>"\xD7\x96", 0xFB37=>"\xEF\xAC\xB7", 0xFB38=>"\xD7\x98", 0xFB39=>"\xD7\x99",
            0xFB3A=>"\xD7\x9A", 0xFB3B=>"\xD7\x9B", 0xFB3C=>"\xD7\x9C", 0xFB3D=>"\xEF\xAC\xBD",
            0xFB3E=>"\xD7\x9E", 0xFB3F=>"\xEF\xAC\xBF", 0xFB40=>"\xD7\xA0", 0xFB41=>"\xD7\xA1",
            0xFB42=>"\xEF\xAD\x82", 0xFB43=>"\xD7\xA3", 0xFB44=>"\xD7\xA4", 0xFB45=>"\xEF\xAD\x85",
            0xFB46=>"\xD7\xA6", 0xFB47=>"\xD7\xA7", 0xFB48=>"\xD7\xA8", 0xFB49=>"\xD7\xA9",
            0xFB4A=>"\xD7\xAA", 0xFB4B=>"\xD7\x95", 0xFB4C=>"\xD7\x91", 0xFB4D=>"\xD7\x9B",
            0xFB4E=>"\xD7\xA4", 0xFB4F=>"\xD7\x90\xD7\x9C", 0xFF01=>"!", 0xFF02=>"\"", 0xFF03=>"#",
            0xFF04=>"\$", 0xFF05=>"%", 0xFF06=>"&", 0xFF07=>"'", 0xFF08=>"(", 0xFF09=>")",
            0xFF0A=>"*", 0xFF0B=>"+", 0xFF0C=>",", 0xFF0D=>"-", 0xFF0E=>".", 0xFF0F=>"/",
            0xFF10=>"0", 0xFF11=>"1", 0xFF12=>"2", 0xFF13=>"3", 0xFF14=>"4", 0xFF15=>"5",
            0xFF16=>"6", 0xFF17=>"7", 0xFF18=>"8", 0xFF19=>"9", 0xFF1A=>":", 0xFF1B=>";",
            0xFF1C=>"<", 0xFF1D=>"=", 0xFF1E=>">", 0xFF1F=>"?", 0xFF20=>"@", 0xFF21=>"A",
            0xFF22=>"B", 0xFF23=>"C", 0xFF24=>"D", 0xFF25=>"E", 0xFF26=>"F", 0xFF27=>"G",
            0xFF28=>"H", 0xFF29=>"I", 0xFF2A=>"J", 0xFF2B=>"K", 0xFF2C=>"L", 0xFF2D=>"M",
            0xFF2E=>"N", 0xFF2F=>"O", 0xFF30=>"P", 0xFF31=>"Q", 0xFF32=>"R", 0xFF33=>"S",
            0xFF34=>"T", 0xFF35=>"U", 0xFF36=>"V", 0xFF37=>"W", 0xFF38=>"X", 0xFF39=>"Y",
            0xFF3A=>"Z", 0xFF3B=>"[", 0xFF3C=>"\\", 0xFF3D=>"]", 0xFF3E=>"^", 0xFF3F=>"_",
            0xFF40=>"`", 0xFF41=>"A", 0xFF42=>"B", 0xFF43=>"C", 0xFF44=>"D", 0xFF45=>"E",
            0xFF46=>"F", 0xFF47=>"G", 0xFF48=>"H", 0xFF49=>"I", 0xFF4A=>"J", 0xFF4B=>"K",
            0xFF4C=>"L", 0xFF4D=>"M", 0xFF4E=>"N", 0xFF4F=>"O", 0xFF50=>"P", 0xFF51=>"Q",
            0xFF52=>"R", 0xFF53=>"S", 0xFF54=>"T", 0xFF55=>"U", 0xFF56=>"V", 0xFF57=>"W",
            0xFF58=>"X", 0xFF59=>"Y", 0xFF5A=>"Z", 0xFF5B=>"{", 0xFF5C=>"|", 0xFF5D=>"}",
            0xFF5E=>"~",
        );

        private static $combining = array(
            0x0300=>1, 0x0301=>1, 0x0302=>1, 0x0303=>1, 0x0304=>1, 0x0305=>1, 0x0306=>1, 0x0307=>1,
            0x0308=>1, 0x0309=>1, 0x030A=>1, 0x030B=>1, 0x030C=>1, 0x030D=>1, 0x030E=>1, 0x030F=>1,
            0x0310=>1, 0x0311=>1, 0x0312=>1, 0x0313=>1, 0x0314=>1, 0x0315=>1, 0x0316=>1, 0x0317=>1,
            0x0318=>1, 0x0319=>1, 0x031A=>1, 0x031B=>1, 0x031C=>1, 0x031D=>1, 0x031E=>1, 0x031F=>1,
            0x0320=>1, 0x0321=>1, 0x0322=>1, 0x0323=>1, 0x0324=>1, 0x0325=>1, 0x0326=>1, 0x0327=>1,
            0x0328=>1, 0x0329=>1, 0x032A=>1, 0x032B=>1, 0x032C=>1, 0x032D=>1, 0x032E=>1, 0x032F=>1,
            0x0330=>1, 0x0331=>1, 0x0332=>1, 0x0333=>1, 0x0334=>1, 0x0335=>1, 0x0336=>1, 0x0337=>1,
            0x0338=>1, 0x0339=>1, 0x033A=>1, 0x033B=>1, 0x033C=>1, 0x033D=>1, 0x033E=>1, 0x033F=>1,
            0x0340=>1, 0x0341=>1, 0x0342=>1, 0x0343=>1, 0x0344=>1, 0x0345=>1, 0x0346=>1, 0x0347=>1,
            0x0348=>1, 0x0349=>1, 0x034A=>1, 0x034B=>1, 0x034C=>1, 0x034D=>1, 0x034E=>1, 0x0350=>1,
            0x0351=>1, 0x0352=>1, 0x0353=>1, 0x0354=>1, 0x0355=>1, 0x0356=>1, 0x0357=>1, 0x0358=>1,
            0x0359=>1, 0x035A=>1, 0x035B=>1, 0x035C=>1, 0x035D=>1, 0x035E=>1, 0x035F=>1, 0x0360=>1,
            0x0361=>1, 0x0362=>1, 0x0363=>1, 0x0364=>1, 0x0365=>1, 0x0366=>1, 0x0367=>1, 0x0368=>1,
            0x0369=>1, 0x036A=>1, 0x036B=>1, 0x036C=>1, 0x036D=>1, 0x036E=>1, 0x036F=>1, 0xFB1E=>1,
        );

    /** @var int   non-ASCII codepoints seen outside $fold since the last reset. */
    private static $unmapped_count = 0;
    /** @var array codepoint => occurrences, for logging a drifted feed. */
    private static $unmapped_seen = array();

    /**
     * Codepoints this build could not fold.  Part 2's cron logs this after an
     * import: a non-zero tally means feed data has moved outside the region
     * the differential gate covers, and the key it produced is untested.
     */
    public static function unmapped() {
        return array( 'count' => self::$unmapped_count, 'codepoints' => self::$unmapped_seen );
    }

    public static function reset_unmapped() {
        self::$unmapped_count = 0;
        self::$unmapped_seen  = array();
    }

    /** UTF-8 to codepoints.  No mbstring. */
    private static function codepoints( $s ) {
        $cp = array();
        $n  = strlen( $s );
        $i  = 0;
        while ( $i < $n ) {
            $c = ord( $s[ $i ] );
            if ( $c < 0x80 ) {
                $cp[] = $c; $i += 1;
            } elseif ( ( $c & 0xE0 ) === 0xC0 && $i + 1 < $n ) {
                $cp[] = ( ( $c & 0x1F ) << 6 ) | ( ord( $s[ $i + 1 ] ) & 0x3F );
                $i += 2;
            } elseif ( ( $c & 0xF0 ) === 0xE0 && $i + 2 < $n ) {
                $cp[] = ( ( $c & 0x0F ) << 12 ) | ( ( ord( $s[ $i + 1 ] ) & 0x3F ) << 6 )
                      | ( ord( $s[ $i + 2 ] ) & 0x3F );
                $i += 3;
            } elseif ( ( $c & 0xF8 ) === 0xF0 && $i + 3 < $n ) {
                $cp[] = ( ( $c & 0x07 ) << 18 ) | ( ( ord( $s[ $i + 1 ] ) & 0x3F ) << 12 )
                      | ( ( ord( $s[ $i + 2 ] ) & 0x3F ) << 6 ) | ( ord( $s[ $i + 3 ] ) & 0x3F );
                $i += 4;
            } else {
                $cp[] = $c; $i += 1;   /* malformed lead byte: pass the byte through */
            }
        }
        return $cp;
    }

    private static function encode( $cp ) {
        if ( $cp < 0x80 )    { return chr( $cp ); }
        if ( $cp < 0x800 )   { return chr( 0xC0 | ( $cp >> 6 ) ) . chr( 0x80 | ( $cp & 0x3F ) ); }
        if ( $cp < 0x10000 ) {
            return chr( 0xE0 | ( $cp >> 12 ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) )
                 . chr( 0x80 | ( $cp & 0x3F ) );
        }
        return chr( 0xF0 | ( $cp >> 18 ) ) . chr( 0x80 | ( ( $cp >> 12 ) & 0x3F ) )
             . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
    }

    /** Python: re.sub(r"\s+", " ", (s or "").strip()) */
    public static function squash( $s ) {
        return preg_replace( '/\s+/', ' ', trim( (string) $s ) );
    }

    /** Python: NFKD, drop combining marks, upper, squash. */
    public static function norm_text( $s ) {
        $out = '';
        foreach ( self::codepoints( (string) $s ) as $cp ) {
            if ( $cp < 0x80 ) {
                $out .= chr( $cp );
            } elseif ( isset( self::$combining[ $cp ] ) ) {
                continue;
            } elseif ( isset( self::$fold[ $cp ] ) ) {
                $out .= self::$fold[ $cp ];
            } else {
                self::$unmapped_count += 1;
                if ( isset( self::$unmapped_seen[ $cp ] ) ) {
                    self::$unmapped_seen[ $cp ] += 1;
                } else {
                    self::$unmapped_seen[ $cp ] = 1;
                }
                $out .= self::encode( $cp );
            }
        }
        return self::squash( strtoupper( $out ) );
    }

    /**
     * Python norm_street.  Returns array( street_key, unit_token ).
     * The unit is held OUT of the key deliberately: one building is one Google
     * place, and a suite number that drifts between feeds must not split a
     * dealer in two.
     */
    public static function norm_street( $s ) {
        $s = str_replace( '&', ' AND ', self::norm_text( $s ) );
        $s = preg_replace( '/[.,]/', ' ', $s );
        $s = preg_replace( '/\bU\s*S\s*HIGHWAY\b|\bUS\s*HWY\b|\bUS-\b/', 'US HWY ', $s );
        $s = preg_replace( '/\bSTATE\s+(HIGHWAY|HWY|ROUTE|RTE|RD)\b/', 'STATE HWY', $s );
        $s = preg_replace( '/\bCOUNTY\s+(ROAD|RD|ROUTE|RTE)\b/', 'COUNTY RD', $s );
        $s = preg_replace( '/[^A-Z0-9# ]+/', ' ', $s );

        $t      = self::squash( $s );
        $tokens = ( '' === $t ) ? array() : explode( ' ', $t );
        $unit   = array();
        $keep   = array();
        $n      = count( $tokens );
        $i      = 0;
        while ( $i < $n ) {
            $tok = $tokens[ $i ];
            if ( in_array( $tok, self::$UNIT_WORDS, true ) || 0 === strpos( $tok, '#' ) ) {
                $unit[] = $tok;
                if ( $i + 1 < $n ) {
                    $unit[] = $tokens[ $i + 1 ];
                    $i += 1;
                }
            } elseif ( isset( self::$DIRECTIONALS[ $tok ] ) ) {
                $keep[] = self::$DIRECTIONALS[ $tok ];
            } elseif ( isset( self::$SUFFIXES[ $tok ] ) ) {
                $keep[] = self::$SUFFIXES[ $tok ];
            } else {
                $keep[] = $tok;
            }
            $i += 1;
        }
        return array( self::squash( implode( ' ', $keep ) ), self::squash( implode( ' ', $unit ) ) );
    }

    public static function norm_state( $raw ) {
        $s = str_replace( '.', '', self::norm_text( $raw ) );
        if ( 2 === strlen( $s ) && ( isset( self::$US_CODES[ $s ] ) || isset( self::$CA_CODES[ $s ] ) ) ) {
            return $s;
        }
        if ( isset( self::$US_STATES[ $s ] ) )    { return self::$US_STATES[ $s ]; }
        if ( isset( self::$CA_PROVINCES[ $s ] ) ) { return self::$CA_PROVINCES[ $s ]; }
        return $s;
    }

    /** Returns array( normalised postal, inferred country or '' ). */
    public static function norm_postal( $raw ) {
        $t = self::norm_text( $raw );
        $s = str_replace( array( ' ', '-' ), '', $t );
        if ( preg_match( '/^([A-Z]\d[A-Z])\s*(\d[A-Z]\d)/', str_replace( '-', ' ', $t ), $m ) ) {
            return array( $m[1] . $m[2], 'CA' );
        }
        if ( preg_match( '/^[A-Z]\d[A-Z]\d[A-Z]\d$/', $s ) ) { return array( $s, 'CA' ); }
        if ( preg_match( '/(\d{5})/', $s, $m ) )              { return array( $m[1], 'US' ); }
        return array( $s, '' );
    }

    public static function norm_country( $raw, $state, $postal_hint ) {
        $s = str_replace( '.', '', self::norm_text( $raw ) );
        if ( in_array( $s, array( 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA' ), true ) ) {
            return 'US';
        }
        if ( in_array( $s, array( 'CA', 'CAN', 'CANADA' ), true ) ) { return 'CA'; }
        if ( '' !== $postal_hint ) { return $postal_hint; }
        if ( isset( self::$CA_CODES[ $state ] ) && ! isset( self::$US_CODES[ $state ] ) ) { return 'CA'; }
        if ( isset( self::$US_CODES[ $state ] ) ) { return 'US'; }
        return ( '' === $s ) ? '' : $s;
    }

    /**
     * The contract test/diff-address-key.php asserts.
     *
     * @param array $raw keys raw_address, raw_city, raw_state, raw_zip, raw_country.
     * @return array keys street_key, unit, city_key, state, zip, postal_country,
     *               country, basis, dealer_key.
     */
    public static function vector( array $raw ) {
        $get = function ( $k ) use ( $raw ) {
            return isset( $raw[ $k ] ) ? (string) $raw[ $k ] : '';
        };

        list( $street, $unit ) = self::norm_street( $get( 'raw_address' ) );
        list( $postal, $pc )   = self::norm_postal( $get( 'raw_zip' ) );

        $state    = self::norm_state( $get( 'raw_state' ) );
        $country  = self::norm_country( $get( 'raw_country' ), $state, $pc );
        $city_key = str_replace( '.', '', self::norm_text( $get( 'raw_city' ) ) );
        $basis    = implode( '|', array( $country, $state, $city_key, $postal, $street ) );

        return array(
            'street_key'     => $street,
            'unit'           => $unit,
            'city_key'       => $city_key,
            'state'          => $state,
            'zip'            => $postal,
            'postal_country' => $pc,
            'country'        => $country,
            'basis'          => $basis,
            'dealer_key'     => substr( sha1( $basis ), 0, 12 ),
        );
    }

    /** Convenience: the key alone, for callers that need nothing else. */
    public static function dealer_key( array $raw ) {
        $v = self::vector( $raw );
        return $v['dealer_key'];
    }
}

endif;
