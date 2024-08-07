import { createRoot, render, StrictMode, createInterpolateElement } from '@wordpress/element';
import { Button, TextControl, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';

import "./scss/style.scss"

const domElement = document.getElementById( window.wpmudevPluginTest.dom_element_id );

const WPMUDEV_PluginTest = () => {

    const [ clientID, setClientID ] = useState('');
    const [ clientSecret, setClientSecret ] = useState('');

    const [ successMsg, setSuccessMsg ] = useState('');
    const [ errorMsg, setErrorMsg ] = useState('')

    const handleClick = () => {
        const endpoint = 'http://localhost/wordpress/wp-json/wpmudev/v1/auth/auth-url';
        const requestBody = {
            clientID,
            clientSecret
        };

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestBody),
        })
        .then(response => {
            if (response.ok) {
                setSuccessMsg('Save successful');
                setErrorMsg('');
            } else {
                setErrorMsg('Save failed');
                setSuccessMsg('')
            }
        })
        .catch(error => {
            setErrorMsg('Error during save:' + error);
            setSuccessMsg('');
        });
    }

    return (
    <>
        <div class="sui-header">
            <h1 class="sui-header-title">
                Settings
            </h1>
      </div>

        <div className="sui-box">

            <div className="sui-box-header">
                <h2 className="sui-box-title">Set Google credentials</h2>
            </div>
            { successMsg != '' && <Notice status="success"><p>{successMsg}</p></Notice> }
            { errorMsg != '' && <Notice status="error"><p>{errorMsg}</p></Notice> }
            <div className="sui-box-body">
                <div className="sui-box-settings-row">
                    <TextControl
                        help={createInterpolateElement(
                            'You can get Client ID from <a>here</a>.',
                            {
                              a: <a href="https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid"/>,
                            }
                          )}
                        label="Client ID"
                        value={clientID}
                        onChange={setClientID}
                    />
                </div>

                <div className="sui-box-settings-row">
                    <TextControl
                        help={createInterpolateElement(
                            'You can get Client Secret from <a>here</a>.',
                            {
                              a: <a href="https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid"/>,
                            }
                          )}
                        label="Client Secret"
                        value={clientSecret}
                        type="password"
						onChange={setClientSecret}
                    />
                </div>

                <div className="sui-box-settings-row">
                    <span>Please use this url <em>{window.wpmudevPluginTest.returnUrl}</em> in your Google API's <strong>Authorized redirect URIs</strong> field</span>
                </div>
            </div>

            <div className="sui-box-footer">
                <div className="sui-actions-right">
                    <Button
                        variant="primary"
                        onClick={ handleClick }
                    >
                        Save
                    </Button>

                </div>
            </div>

        </div>

    </>
    );
}

if ( createRoot ) {
    createRoot( domElement ).render(<StrictMode><WPMUDEV_PluginTest/></StrictMode>);
} else {
    render( <StrictMode><WPMUDEV_PluginTest/></StrictMode>, domElement );
}
