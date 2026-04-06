import React, {useEffect, useState} from 'react';
import axios from "axios";
import {DateTime} from "luxon";
import {__} from '@wordpress/i18n';
import {Alert, Card, CardContent, CardHeader, Snackbar} from "@mui/material";
import {DataGrid, GridToolbar} from "@mui/x-data-grid";
import {BarElement, CategoryScale, Chart as ChartJS, LinearScale, Title, Tooltip} from "chart.js";
import {Bar} from 'react-chartjs-2';
import BasicReportFilters from "@components/reports/BasicReportFilters";

/**
 * Location Search Report Card
 * @returns {JSX.Element}
 * @constructor
 */
const LocationSearchReport = () => {
    // -- General UX
    const [sbOpen, setSBOpen] = useState(false); // Alerts open/close handler
    const [sbMsg, setSBMsg] = useState(''); // Alerts message

    // -- Data grid
    const columns = [
        {field: 'slp_repq_address', headerName: 'Address'},
        {field: 'QueryCount', headerName: 'Search Count', type: 'number'}
    ];
    const [requestData, setRequestData] = useState({
        start: DateTime.now().plus({months: -1}),
        end: DateTime.now(),
        limit: 500,
    });
    const [data, setData] = useState([]); // rest API response records
    const [metaData, setMetaData] = useState({}); // rest API response meta
    const [pageSize, setPageSize] = useState(10);
    const [loading, setLoading] = useState(false);

    // -- Chart
    //
    ChartJS.register(BarElement, CategoryScale, LinearScale, Title, Tooltip);
    const chartOptions = {
        scales: {y: {beginAtZero: true}},
        plugins: {
            title: {
                display: true,
                text: __('Daily Count', 'slp-power'),
            }
        }
    }
    const [chartLoading, setChartLoading] = useState(false);
    const [chartLabels, setChartLabels] = useState([]);
    const [chartData, setChartData] = useState([]);

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
     * Data Grid - On startup or if data drivers (start/end date) change.
     */
    useEffect(() => {
        function getLocationSearchHistory() {
            setLoading(true);

            // -- async fetch search history
            axios.get(
                `${slpReact.url.rest}store-locator-plus/report/location/search_history`,
                {
                    params: {
                        ...requestData,
                        start: requestData.start.toISODate(),
                        end: requestData.end.toISODate()
                    }
                }
            )
                // -- response received
                // .data is the body of the response
                .then((response) => {

                    // if status code is not 201, something is wrong
                    if (response.status !== 201) {
                        throw new Error(response.data);
                    }

                    setData(response.data.records);
                    setMetaData(response.data.metadata);
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

        getLocationSearchHistory();
    }, [requestData.start, requestData.end, requestData.limit]);

    /**
     * Chart -- on startup
     */
    useEffect(() => {
        function getLocationSearchHistory() {
            setChartLoading(true);

            // -- async fetch search history
            axios.get(
                `${slpReact.url.rest}store-locator-plus/report/location/search_history_count`,
                {
                    params: {
                        ...requestData,
                        start: requestData.start.toISODate(),
                        end: requestData.end.toISODate()
                    }
                }
            )
                // -- response received
                // .data is the body of the response
                .then((response) => {

                    // if status code is not 201, something is wrong
                    if (response.status !== 201) {
                        throw new Error(response.data);
                    }

                    const labels = [];
                    const values = [];
                    response.data.records.map((record) => {
                        labels.push(record.date);
                        values.push(record.count);
                    });

                    setChartLabels(labels);
                    setChartData(values);
                })

                // -- something broke
                .catch((error) => {
                    setSBMsg(error.message);
                    setSBOpen(true);
                    console.log(error);
                })

                // -- always do this
                .then(() => {
                    setChartLoading(false);
                });

        }

        getLocationSearchHistory();
    }, [requestData.start, requestData.end]);


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
            <Card raised>
                <CardHeader title={__('Location Searches', 'slp-power')}/>
                <CardContent>
                    <BasicReportFilters requestData={requestData} setRequestData={setRequestData}/>
                    <DataGrid
                        autoHeight
                        columns={columns}
                        rows={data}
                        getRowId={row => row.slp_repq_address}
                        pageSize={pageSize}
                        rowsPerPageOptions={[10, 20, 50, 100]}
                        onPageSizeChange={(newPageSize) => setPageSize(newPageSize)}
                        loading={loading}
                        components={{Toolbar: GridToolbar}}
                        initialState={{
                            sorting: {
                                sortModel: [{field: 'QueryCount', sort: 'desc'}],
                            },
                        }}
                    />
                    <Bar data={{
                        labels: chartLabels,
                        datasets: [{
                            data: chartData,
                            backgroundColor: 'rgba(128,128,255, 0.6)',
                        }]
                    }} options={chartOptions}/>
                </CardContent>
            </Card>
        </>
    );
}

export default LocationSearchReport;