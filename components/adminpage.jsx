import React from 'react';
import { TabView,TabPanel } from 'primereact/tabview';
import TemplateTab from "./templatetab";
import GenericTab from "./generictab";

import { api_list } from "./api.js";

export default class AdminPage extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.state = {
            templates: [],
        };
    }
    componentDidMount = () => {
        this.onChange();
    }

    onChange = () => {
        // reload all templates
        api_list('index','item',{ sort: 'n', filter: { type:'template' }})
            .then(json => {
                if(json) this.setState({ "templates": json.data.list });
        });
    }

    render() {
        return (
<TabView id="wppresencetabs" animate={true} large={true} defaultSelectedTabId="templates">
    <TabPanel id="templates" header="Templates"><TemplateTab onChange={this.onChange} /></TabPanel>
    {this.state.templates.map((tmp,idx) => (
        <TabPanel id={tmp.type} header={tmp.name} key={idx}><GenericTab template={tmp} /></TabPanel>
    ))}
</TabView>);
    }
}
