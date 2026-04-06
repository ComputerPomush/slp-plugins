import React from 'react';
import {render} from '@wordpress/element';
import {CssBaseline} from "@mui/material";
import AdminHeader from "@components/AdminHeader";

/**
 * New Header for old SLP Admin Pages
 * @returns {JSX.Element}
 * @constructor
 */
const NewHeader = () => {
    // -- Render the info page
    return (<>
        <CssBaseline/>
        <AdminHeader/>
    </>);
}

render(<NewHeader/>, document.getElementById('dashboard-header'));