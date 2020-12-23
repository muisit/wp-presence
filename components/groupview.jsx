import React from 'react';
import { api_list, api_misc } from "./api.js";
import { Button } from 'primereact/button';
import { Calendar } from 'primereact/calendar';
import { Checkbox } from 'primereact/checkbox';

export default class GroupView extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.abortType='frontend';
    }

    markPresence = (state, item,checked) => {
        var itemsbyid=Object.assign({},this.props.byId);
        itemsbyid[item.id].checked=checked;
        if(checked) {
            itemsbyid[item.id].presence=state;
        }
        else {
            itemsbyid[item.id].presence="present";
        }
        this.props.onChange(itemsbyid);

        var dt=new Date(this.props.date);
        dt = dt.getFullYear() + "-" + (1+dt.getMonth()) + "-" + dt.getDate();
        // backend call
        api_misc(this.abortType,'item','mark',{ model: item, date: dt, state: state, checked: checked});
    }

    showDetail = (item) => {
        this.props.showElement(item);
    }

    render() {
        if(this.props.group.length > 0) {
            return (
                <ul>
                    {this.props.group.map((el,idx2) => (
                        <li key={this.props.idx + idx2} className={this.props.byId[el.id].presence}>
                            <Checkbox inputId={this.props.idx+'#' + idx2} onChange={(e) => this.markPresence('present', el, e.target.checked)} checked={this.props.byId[el.id].checked}/>
                            <label htmlFor={this.props.idx+'#'+idx2}>{el.name}</label>
                            <span className="icon">
                              <a onClick={(e) => this.markPresence('absent', el,true)}><i className="pi pi-thumbs-down" /></a>
                              <a onClick={(e) => this.showDetail(el)}><i className="pi pi-search" /></a>
                            </span>
                        </li>
                    ))}
                </ul>
            )
        }
        else if(Object.keys(this.props.group).length > 0) {
            // this is a list of grouped values
            return (
                <div>
                    {Object.keys(this.props.group).map((el,idx2) => (<div key={this.props.idx + '_' +idx2}>
                    <h4>{el}</h4>
                    <GroupView showElement={this.props.showElement} date={this.props.date} template={this.props.template} group={this.props.group[el]} byId={this.props.byId} idx={this.props.idx + '_' + idx2} onChange={this.props.onChange}/>
                    </div>
                    ))}
                </div>
            )
        }
        else {
            return (<div></div>);
        }
    }
}
