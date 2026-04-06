import React, {useRef, useState} from 'react';
import {render} from '@wordpress/element';
import {__} from '@wordpress/i18n';
import {Box, CssBaseline, Unstable_Grid2 as Grid} from "@mui/material";
import AdminHeader from "@components/AdminHeader";
import LatestNews from "@components/info/LatestNews";
import Documentation from "@components/info/Documentation";
import AdminDataDrawer from "@components/AdminDataDrawer";

/**
 * The main report panel component.
 * @returns {JSX.Element}
 * @constructor
 */
const ReportPanel = () => {
    const containerRef = useRef(null);
    const [envDrawerOpen, setEnvDrawerOpen] = useState(false);
    const [scheduleDrawerOpen, setScheduleDrawerOpen] = useState(false);

    // -- setup the main menu
    let mainButtons = [];

    // only show these if not in the SaaS
    if (!slpReact.env.mySLP) {
        // -- define the menu items
        mainButtons = [
            {
                children: __('Environment', 'store-locator-le'),
                props: {
                    key: 'tab_environment',
                    onClick: () => {
                        setEnvDrawerOpen(!envDrawerOpen)
                    }
                }
            },
            {
                children: __('Schedule', 'store-locator-le'),
                props: {
                    key: 'tab_schedule',
                    onClick: () => {
                        setScheduleDrawerOpen(!scheduleDrawerOpen)
                    }
                }
            },
        ];
    }

    // -- Render the info page
    return (<>
        <CssBaseline/>
        <AdminHeader mainButtons={mainButtons}/>
        <Box m={2}>
            <Grid container spacing={2} ref={containerRef}>
                <Grid sm={12} md={8}>
                    <Documentation/>
                </Grid>
                <Grid sm={12} md={4}>
                    <LatestNews/>
                </Grid>
            </Grid>
        </Box>
        <AdminDataDrawer
            Endpoint="store-locator-plus/v2/environment/"
            Title={__('Environment', 'store-locator-le')}
            open={envDrawerOpen}
            onClose={() => setEnvDrawerOpen(false)}
            containerRef={containerRef}
        />
        <AdminDataDrawer
            Endpoint="store-locator-plus/v2/schedule/"
            Title={__('Schedule', 'store-locator-le')}
            open={scheduleDrawerOpen}
            onClose={() => setScheduleDrawerOpen(false)}
            containerRef={containerRef}
        />
    </>);
}

render(<ReportPanel/>, document.getElementById('slp-info-tab'));