import React, {useEffect, useState} from 'react';
import {__} from '@wordpress/i18n';
import {Alert, Card, CardContent, CardHeader, Snackbar} from "@mui/material";
import {DataGrid, GridToolbar} from "@mui/x-data-grid";
import {DateTime} from "luxon";
import axios from "axios";
import BasicReportFilters from "@components/reports/BasicReportFilters";
import {Bar} from "react-chartjs-2";
import {BarElement, CategoryScale, Chart as ChartJS, LinearScale, Title, Tooltip} from "chart.js";

/**
 * Location result report card.
 * @returns {JSX.Element}
 * @constructor
 */
const LocationResultsReport = () => {
    // -- General UX
    const [sbOpen, setSBOpen] = useState(false); // Alerts open/close handler
    const [sbMsg, setSBMsg] = useState(''); // Alerts message

    // -- Data grid
    const columns = [
        {field: 'sl_id', headerName: 'ID', type: 'number'},
        {field: 'sl_store', headerName: 'Location'},
        {field: 'sl_address', headerName: 'Address'},
        {field: 'sl_city', headerName: 'City'},
        {field: 'sl_state', headerName: 'State'},
        {field: 'sl_zip', headerName: 'Postal'},
        {field: 'ResultCount', headerName: 'Result Count', type: 'number'},
    ];
    const [requestData, setRequestData] = useState({
        start: DateTime.now().plus({months: -1}),
        end: DateTime.now(),
        limit: 500,
    });
    const [data, setData] = useState([]);
    const [metaData, setMetaData] = useState({});
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
        function getLocationResultHistory() {
            setLoading(true);

            // -- async fetch search history
            axios.get(
                `${slpReact.url.rest}store-locator-plus/report/location/search_result_history`,
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

        getLocationResultHistory();
    }, [requestData.start, requestData.end, requestData.limit]);

    /**
     * Chart -- on startup
     */
    useEffect(() => {
        function getLocationSearchHistory() {
            setChartLoading(true);

            // -- async fetch search history
            axios.get(
                `${slpReact.url.rest}store-locator-plus/report/location/result_history_count`,
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
                <CardHeader title={__('Location Results', 'slp-power')}/>
                <CardContent>
                    <BasicReportFilters requestData={requestData} setRequestData={setRequestData}/>
                    <DataGrid
                        autoHeight
                        columns={columns}
                        rows={data}
                        getRowId={row => row.sl_id}
                        pageSize={pageSize}
                        rowsPerPageOptions={[10, 20, 50, 100]}
                        onPageSizeChange={(newPageSize) => setPageSize(newPageSize)}
                        loading={loading}
                        components={{Toolbar: GridToolbar}}
                        initialState={{
                            sorting: {
                                sortModel: [{field: 'ResultCount', sort: 'desc'}],
                            },
                        }}
                    />
                    <Bar data={{
                        labels: chartLabels,
                        datasets: [{
                            data: chartData,
                            backgroundColor: 'rgba(255,128,128, 0.6)',
                        }]
                    }} options={chartOptions}/>
                </CardContent>
            </Card>
        </>
    );
}

export default LocationResultsReport;