import React from 'react';
import FrontendView from "./frontendview";
import LoginView from "./loginview";

export default class FrontEndPage extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.state = {
            view_fe:false
        };
    }
    login = (state) => {
        console.log("login state is ",state);
        this.setState({view_fe:state});
    }

    render() {
        return (
        <div className='admin-root'>
            {this.state.view_fe && (<FrontendView/>)}
            {!this.state.view_fe && (<LoginView onChange={this.login}/>)}
        </div>);
    }
}
