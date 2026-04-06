import React from 'react';
import FormControl from '@mui/material/FormControl';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import {AdapterLuxon} from '@mui/x-date-pickers/AdapterLuxon';
import {DatePicker} from '@mui/x-date-pickers/DatePicker';
import {LocalizationProvider} from '@mui/x-date-pickers/LocalizationProvider';


/**
 *
 * @returns {JSX.Element}
 * @constructor
 */
const BasicReportFilters = ({requestData, setRequestData}) => {

    return (
        <LocalizationProvider dateAdapter={AdapterLuxon}>
            <Stack direction="row" spacing={0.5} mb={1}>
                <FormControl variant="standard" className="slpDatePicker_FC">
                    <DatePicker
                        DateInputProps={{className: "slpDatePicker_DIP"}}
                        className="slpDatePicker_DP"
                        label="Start"
                        maxDate={requestData.end}
                        value={requestData.start}
                        onChange={(newValue) => {
                            setRequestData(requestData => ({
                                ...requestData,
                                start: newValue
                            }));
                        }}
                        renderInput={(params) => <TextField {...params} />}
                    />
                </FormControl>
                <DatePicker
                    className="slpDatePicker_DP"
                    label="End"
                    minDate={requestData.start}
                    value={requestData.end}
                    onChange={(newValue) => {
                        setRequestData(requestData => ({
                            ...requestData,
                            end: newValue
                        }));
                    }}
                    renderInput={(params) => <TextField {...params} />}
                />
            </Stack>
        </LocalizationProvider>
    );
}

export default BasicReportFilters;