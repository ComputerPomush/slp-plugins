import React from 'react';
import {__} from '@wordpress/i18n';
import {AppBar, Button, ButtonGroup, IconButton, Toolbar, Tooltip, Typography} from "@mui/material";
import {MenuBook} from "@mui/icons-material";

/**
 * The admin header react component.
 * @returns {JSX.Element}
 * @constructor
 */
const AdminHeader = ({docLink = '', mainButtons = []}) => {
    const documentationLink = `${slpReact.url.slp_documentation}${docLink}`;

    let mainButtonGroup = null
    if (mainButtons && mainButtons.length) {
        mainButtonGroup = (
            <ButtonGroup variant="text" size="small" color="inherit">
                {mainButtons.map((button => (<Button {...button.props}>{button.children}</Button>)))}
            </ButtonGroup>
        );
    }

    return (
        <AppBar position="sticky" sx={{top: "32px"}}>
            <Toolbar>
                <Typography variant="h6" component="div"
                            sx={{flexGrow: 1}}>{slpReact.pageName}</Typography>
                {mainButtonGroup}
                <Tooltip title={__('Documentation', 'store-locator-le')}>
                    <IconButton color="inherit" size="medium" variant="outlined"
                                href={documentationLink}
                                target="_blank"
                                aria-label={__('Documentation', 'store-locator-le')}>
                        <MenuBook/>
                    </IconButton>
                </Tooltip>
            </Toolbar>
        </AppBar>
    );
}

export default AdminHeader;