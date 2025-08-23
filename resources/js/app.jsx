import './bootstrap';
import React from 'react';
import ReactDOM from 'react-dom/client';

function App() {
    return <h1>Hello from React inside Laravel 🎉</h1>;
}

if (document.getElementById('app')) {
    const root = ReactDOM.createRoot(document.getElementById('app'));
    root.render(
        <React.StrictMode>
            <App />
        </React.StrictMode>
    );
}
