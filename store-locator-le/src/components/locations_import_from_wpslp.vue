<template>
    <v-alert v-for="notice in notices" v-bind:value="true" v-bind:key="notice.id" type="info" dismissible>
        {{notice}}
    </v-alert>

    <v-container fluid>
        <v-layout row>
            <v-flex xs3>
                <v-switch
                        color="info"
                        class="pt-3"
                        v-bind:label="boolean.site_import_protocol ? 'https://' : 'http://'"
                        v-model="boolean.site_import_protocol"
                ></v-switch>
            </v-flex>
            <v-flex>
                <v-text-field
                        required
                        name="site_import_url"
                        id="site_import_url"
                        v-model="site_import_url"
                        v-bind:hint="text.site_import_url_hint"
                        v-bind:label="text.site_import_url"
                ></v-text-field>
            </v-flex>
        </v-layout>
        <v-layout row>
            <v-btn
                    v-bind:loading="boolean.loading_locations"
                    v-bind:disabled="boolean.loading_locations"
                    color="secondary"
                    v-on:click.native="load_locations"
            >
                {{text.load}}
                <v-icon>cloud-download</v-icon>
            </v-btn>
        </v-layout>
    </v-container>

    <v-divider></v-divider>
    <v-list v-for="location in locations" v-bind:key="location.sl_id">
        <v-list-tile>
            <v-list-tile-content>
                <v-list-tile-title>{{location.sl_store}}</v-list-tile-title>
                <v-list-tile-sub-title>{{location.sl_city}}, {{location.sl_state}} {{location.sl_zip}}</v-list-tile-sub-title>
            </v-list-tile-content>
            <v-list-tile-action>
                <v-icon v-if="!location.is_loaded">schedule</v-icon>
                <v-icon v-if="location.is_loaded">check_circle_outline</v-icon>
            </v-list-tile-action>
        </v-list-tile>

    </v-list>
</template>
