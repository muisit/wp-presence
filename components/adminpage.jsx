import React from 'react';
import { TabView,TabPanel } from 'primereact/tabview';
import TemplateTab from "./templatetab";
import GenericTab from "./generictab";
import FrontendView from "./frontendview";

import { api_list } from "./api.js";

export default class AdminPage extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.state = {
            templates: [],
            view_fe:false
        };
    }
    componentDidMount = () => {
        this.onChange();
    }

    onChange = () => {
        console.log("on change called, reloading all templates");
        // reload all templates
        api_list('index','item',{ sort: 'n', filter: { type:'template'}, special: 'with attributes' })
            .then(json => {
                if(json) this.setState({ "templates": json.data.list });
        });
    }

    render() {
        return (
        <div className='admin-root'>
            <div className='admin-header'>
                {!this.state.view_fe && (<div>
                    <a onClick={() => this.setState({view_fe: true})}>Front End</a>
                </div>)}
                {this.state.view_fe && (<div>
                    <a onClick={() => this.setState({view_fe: false})}>Back End</a>
                </div>)}
            </div>
            <div className='admin-body'>
                {this.state.view_fe && (<FrontendView/>)}
                {!this.state.view_fe && (<TabView id="wppresencetabs" animate={true} large={true} defaultSelectedTabId="templates">
    <TabPanel id="templates" header="Templates"><TemplateTab onChange={this.onChange} /></TabPanel>
    {this.state.templates.map((tmp,idx) => (
        <TabPanel id={tmp.type} header={tmp.name} key={idx}><GenericTab template={tmp} /></TabPanel>
    ))}
</TabView>)}
            </div>
        </div>);
    }
}
