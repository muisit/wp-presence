import React from 'react';
import { api_misc } from "../api.js";
import { Checkbox } from 'primereact/checkbox';

export default class GroupView extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.abortType='frontend';
    }

    markPresence = (state, item,checked) => {
        var itemsbyid=Object.assign({},this.props.byId);
        itemsbyid[item.id].data.checked=checked;
        if(checked) {
            itemsbyid[item.id].original.presence=state;
        }
        else {
            itemsbyid[item.id].original.presence="";
        }
        this.props.onChange(itemsbyid);

        var dt=new Date(this.props.date);
        dt = dt.getFullYear() + "-" + (1+dt.getMonth()) + "-" + dt.getDate();
        // backend call
        api_misc(this.abortType,'item','mark',{ model: item.original, date: dt, state: state, checked: checked});
    }

    showDetail = (item) => {
        this.props.showElement(item);
    }

    render() {
        if(this.props.group.length > 0) {
            var displayfields=[];
            this.props.template.attributes.map((attr) => {
                if(attr.remark && attr.remark.display === true) {
                    displayfields.push(attr);
                }
            });
            var items=this.props.group.map((id) => {
                var itm=this.props.byId[id];
                itm._display=[];
                displayfields.map((field) => {
                    if(itm.data[field.name]) {
                        itm._display.push(itm.data[field.name]);
                    }
                    else {
                        itm._display.push('');
                    }
                });
                return itm;
            });
            return (
                <table className='groupitems'>
                    <tbody>
                    {items.map((el,idx2) => (
                        <tr key={this.props.idx + idx2} className={el.original.presence}>
                            <td className='groupcheck'><Checkbox inputId={this.props.idx + '#' + idx2} onChange={(e) => this.markPresence('present', el, e.target.checked)} checked={el.data.checked} /></td>
                            <td><label htmlFor={this.props.idx+'#'+idx2}>{el.original.name}</label></td>
                            {el._display && el._display.length>0 && el._display.map((dsp,idx) => (
                            <td key={this.props.idx + idx2 + '_d' + idx}>{dsp}</td>
                            ))}
                            <td className='groupcheck'>
                                <span className="icon">
                                  <a onClick={(e) => this.markPresence('absent', el,true)}><i className="pi pi-thumbs-down" /></a>
                                </span>
                            </td>
                            <td className='groupcheck'>
                                <span className="icon">
                                  <a onClick={(e) => this.showDetail(el)}><i className="pi pi-search" /></a>
                                </span>
                            </td>
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
