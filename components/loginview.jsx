import React from 'react';
import { api_list, api_misc } from "./api.js";
import { Button } from 'primereact/button';
import { InputText } from 'primereact/inputtext';
import { Password } from 'primereact/password';

export default class LoginView extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.state = {
            username: '',
            password:''
        };
        this.abortType='frontend';
    }

    componentDidMount = () => {
        // try a tentative login to see if we already have a valid session
        api_misc(this.abortType,'user','session',{ username: '', password: ''})
            .then((res) => {
                if(res && res.data && res.data.loggedin) {
                    this.props.onChange(true);
                }
                else {
                    this.props.onChange(false);
                }
            })
            .catch(()=> {
                this.props.onChange(false);
            });
    }


    doLogin = () => {
        api_misc(this.abortType,'user','login',{ username: this.state.username, password: this.state.password})
            .then((res) => {
                if(res && res.data && res.data.loggedin) {
                    return api_misc(this.abortType, 'user','session',{})
                        .then((res) => {
                            if(res && res.data && res.data.nonce) {
                                wppresence.nonce=res.data.nonce;
                                this.props.onChange(true);
                            }
                            else {
                                this.props.onChange(false);
                            }
                        });
                }
                else {
                    this.props.onChange(false);
                }
            })
            .catch(()=> {
                this.props.onChange(false);
            });
    }

    render() {
        return (
            <div className='container'>
            <div className='login-form row'>
                <div className='col-12 offset-md-3 col-md-6'>
                    <InputText value={this.state.username} name='username' className='username fullwidth' onChange={(e) => this.setState({'username':e.target.value})}/><br/>
                    <Password value={this.state.password} name='password' className='password fullwidth' onChange={(e) => this.setState({'password':e.target.value})}/><br/>
                    <Button label="Login" className="p-button-raised p-button-text fullwidth" onClick={this.doLogin} />
                </div>
            </div>
        </div>);
    }
}
