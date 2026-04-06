import React from 'react';
import {__} from '@wordpress/i18n';
import {DateTime} from "luxon";
import {decodeHTML} from "entities";
import {Button, Card, CardActions, CardContent, CardHeader, CardMedia} from "@mui/material";

const ArticleSummary = ({post}) => {
    let cardImage = '';
    if (post.jetpack_featured_media_url) {
        cardImage = (
            <CardMedia
                component="img"
                height="120"
                image={post.jetpack_featured_media_url}
                alt={post.title.rendered}
            />
        );
    }
    const niceDate = DateTime.fromISO(post.date).toLocaleString(DateTime.DATE_MED);

    const stripReadMoreRegex = /<div class="more-link-container">.*?<\/div>/i
    const noReadMeExcerpt = decodeHTML(post.excerpt.rendered).replace(stripReadMoreRegex, '');

    /**
     * Render
     */
    return (
        <Card>
            {cardImage}
            <CardHeader
                title={post.title.rendered}
                titleTypographyProps={{variant: 'h6'}}
                subheader={niceDate}
                subheaderTypographyProps={{variant: 'subtitle2'}}
                sx={{fontWeight: 'bold'}}
            />
            <CardContent sx={{padding: '0 16px'}}>
                <div dangerouslySetInnerHTML={{__html: noReadMeExcerpt}}/>
            </CardContent>
            <CardActions>
                <Button size="small" href={post.link}
                        target="store-locator-plus">{__('Learn More', 'store-locator-le')}</Button>
            </CardActions>
        </Card>
    );
}

export default ArticleSummary;