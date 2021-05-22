import React from 'react';
import { api_list, api_misc } from "./api.js";
import { Button } from 'primereact/button';
import { Calendar } from 'primereact/calendar';
import { Checkbox } from 'primereact/checkbox';

export default class PresenceView extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.abortType='frontend';
    }

    findState = (id,date) => {
        for(var i in this.props.list[date]) {
            var pres = this.props.list[date][i];
            if(pres.item === id) {
                if(pres.state === "present") return "X";
                if(pres.state === "absent") return "A";
                return "O";
            }
        }
        return "";
    }

    render() {
        if(!this.props.list) {
            console.log("no list data");
            return (<div></div>);
        }
        if(this.props.group.length > 0) {
            var dates=Object.keys(this.props.list);
            dates.sort();
            console.log("looping over dates",dates);
            console.log(this.props.byId);
            return (
                <table>
                    <thead>
                        <tr>
                            <th>Naam</th>
                            {dates.map((dt,idx) => (
                                <th key={idx}>{dt.split(' ')[0]}<br/>{dt.split(' ')[1]}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                      {this.props.group.map((el,idx2) => (
                        <tr key={idx2}>
                          <td>{this.props.byId[el] && this.props.byId[el].original.name}</td>
                          {dates.map((dt,idx) => (
                            <td key={idx}>{this.findState(el,dt)}</td>
                            ))}
                        </tr>
                      ))}
                    </tbody>
                </table>
            )
        }
        else if(Object.keys(this.props.group).length > 0) {
            // this is a list of grouped values
            return (
                <div>
                    {Object.keys(this.props.group).map((el,idx2) => (<div key={this.props.idx + '_' +idx2}>
                    <h4>{el}</h4>
                    <PresenceView list={this.props.list} date={this.props.date} template={this.props.template} group={this.props.group[el]} byId={this.props.byId} idx={this.props.idx + '_' + idx2} onChange={this.props.onChange}/>
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
