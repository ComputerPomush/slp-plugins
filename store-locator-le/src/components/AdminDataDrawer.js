import React, {useEffect, useState} from 'react';
import {
    Box,
    Drawer,
    IconButton,
    Skeleton,
    Table,
    TableBody,
    TableCell,
    TableRow,
    Toolbar,
    Typography
} from "@mui/material";
import axios from "axios";
import {Close} from "@mui/icons-material";

/**
 * Generic Admin Data Drawer rendering a table of key/value pairs returned from a REST endpoint.
 *
 * Expects the JS variable "slpReact" to be set before processing.
 *
 * @param {String} Title title for drawer , required
 * @param {String} Endpoint WordPress SLP REST endpoint URL
 * @param {Object} [props]
 * @param {HTMLElement} [containerRef] JSX ref for DOM element to attach drawer to
 * @param {Boolean} open whether or not the drawer is open
 * @param {Function} onClose function to call to close the drawer
 * @returns {JSX.Element}
 * @constructor
 */
const AdminDataDrawer = ({Title, Endpoint, ...props}) => {
    const [data, setData] = useState([]); // rest API response records

    /**
     * Get the data
     */
    useEffect(() => {
        function getData() {
            console.log('Get Data: ', Endpoint, data);

            // -- async fetch env info
            axios.get(`${slpReact.url.rest}${Endpoint}?_wpnonce=${slpReact.nonce}`)
                // -- response received
                // .data is the body of the response
                .then((response) => {

                    // if status code is not 200, something is wrong
                    if (response.status !== 200) {
                        throw new Error(response.data);
                    }
                    setData(response.data);
                })

                // -- something broke
                .catch((error) => {
                    console.log(error);
                });
        }

        if (props.open && data.length < 1) getData();
    }, [props.open]);

    // -- Render
    return (
        <Drawer anchor="right"
                variant="temporary"
                {...props}
                PaperProps={{style: {position: 'absolute'}}}
                BackdropProps={{style: {position: 'absolute'}}}
                ModalProps={{
                    container: props.containerRef.current,
                    style: {position: 'absolute'}
                }}
        >
            <Toolbar sx={{backgroundColor: '#E4E4E4'}}>
                <Typography variant="h6" color="inherit" component="div" sx={{flexGrow: 1}}>
                    {Title}
                </Typography>
                <IconButton aria-label="close">
                    <Close onClick={() => props.onClose()}/>
                </IconButton>
            </Toolbar>
            <Box m={2}>
                <Table size="small">
                    <TableBody>
                        {data.length ? data.map((item) => (
                            <TableRow>
                                <TableCell align="right" variant="head">
                                    {item.label}
                                </TableCell>
                                <TableCell>
                                    <div dangerouslySetInnerHTML={{__html: item.value}}/>
                                </TableCell>
                            </TableRow>

                        )) : (
                            <TableRow>
                                <TableCell>
                                    <Skeleton/>
                                </TableCell>
                                <TableCell>
                                    <Skeleton/>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </Box>
        </Drawer>
    );
}

export default AdminDataDrawer;