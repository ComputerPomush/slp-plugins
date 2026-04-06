import React, {useEffect, useState} from 'react';
import axios from "axios";
import {__} from '@wordpress/i18n';
import {Alert, Card, CardContent, CardHeader, Skeleton, Snackbar, Stack} from "@mui/material";
import ArticleSummary from "@components/info/ArticleSummary";

/**
 * Location Search Report Card
 * @returns {JSX.Element}
 * @constructor
 */
const Documentation = () => {
    // -- General UX
    const [sbOpen, setSBOpen] = useState(false); // Alerts open/close handler
    const [sbMsg, setSBMsg] = useState(''); // Alerts message

    const [data, setData] = useState([]); // rest API response records
    const [loading, setLoading] = useState(false);

    /**
     * Snackbar message close handler
     * @param event
     * @param reason
     */
    const handleClose = (event, reason) => {
        if (reason === 'clickaway') {
            return;
        }

        setSBMsg('');
        setSBOpen(false);
    };

    /**
     * Get the news
     */
    useEffect(() => {
        function getData() {
            setLoading(true);

            // -- async fetch search history
            // category: 2260 = "getting-started"
            // TODO: Switch to REST endpoint for SLP News Feed RSS consumer fetch_feed() includes caching
            axios.get(`${slpReact.url.slp_documentation}/wp-json/wp/v2/posts/?_fields=title,date,excerpt,jetpack_featured_media_url,link&per_page=5&categories=12`)
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
                    setSBMsg(error.message);
                    setSBOpen(true);
                    console.log(error);
                })

                // -- always do this
                .then(() => {
                    setLoading(false);
                });

        }

        getData();
    }, []);

    /**
     * Render
     */
    return (
        <>
            <Snackbar
                open={sbOpen}
                anchorOrigin={{vertical: 'top', horizontal: 'center'}}
                autoHideDuration={3000}
                onClose={handleClose}
            >
                <Alert onClose={handleClose} severity="warning" sx={{width: '100%'}}>
                    {sbMsg}
                </Alert>
            </Snackbar>
            <Card raised loading={loading}>
                <CardHeader title={__('Documentation', 'store-locator-le')}/>
                <CardContent>
                    <Stack spacing={1}>
                        {data.length ? data.map((post) => (
                            <ArticleSummary post={post}/>
                        )) : (
                            <>
                                <Skeleton height={150}/>
                                <Skeleton height={150}/>
                                <Skeleton height={150}/>
                                <Skeleton height={150}/>
                                <Skeleton height={150}/>
                            </>
                        )}
                    </Stack>
                </CardContent>
            </Card>
        </>
    );
}

export default Documentation;