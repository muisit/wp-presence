import React from 'react';
import { api_list, api_misc } from "./api.js";
import { Dropdown } from 'primereact/dropdown';
import { InputText } from 'primereact/inputtext';
import { InputNumber } from 'primereact/inputnumber';
import { Calendar } from 'primereact/calendar';
import { format_date } from './functions';

export default class ElementView extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.abortType='frontend';
    }

    onChangeAttr = (attr,value,isdate) => {
        var newitem = Object.assign({}, this.props.item);
        if(attr.name == 'name') {
          console.log("setting original name");
            newitem.original.name = value;
            newitem.changed=true;
        }
        else {
            console.log("adjusting attribute data");
            if(isdate) {
              value = format_date(value);
            }
            newitem.data[attr.name]=value;
            newitem.changed=true;
        }
        if (this.props.onChange) this.props.onChange(newitem);
    }

    render() {
        if(!this.props.template.attributes) return (null);
        var attrs=this.props.template.attributes.filter(function(a) {
          return a.type != 'byear' && a.type != 'category';
        });
        return (<div>
              <h4>{this.props.item.original.name}</h4>
          <div className='attribute'>
            <label>Name</label>
            <div className='inputs'>
              <div className='input'>
                <InputText className="fullwidth" value={this.props.item.original.name} onChange={(e) => this.onChangeAttr({ name: 'name' }, e.target.value)} />
              </div>
            </div>
          </div>
              {attrs.map((attr,idx) => {
                var value = this.props.item.data && this.props.item.data[attr.name] ? this.props.item.data[attr.name] : '';
                return (
          <div className='attribute' key={idx}>
            <label>{attr.name}</label>
            <div className='inputs'>
              <div className='input'>
              {attr && attr.type === 'string' && (
                <InputText className="fullwidth" value={value} onChange={(e) => this.onChangeAttr(attr,e.target.value)}/>
              )}
              {attr && attr.type === 'number' && (
                <InputNumber className='inputint fullwidth' onChange={(e) => this.onChangeAttr(attr,e.target.value)}
                mode="decimal" inputMode='decimal' minFractionDigits={1} maxFractionDigits={5} min={0} useGrouping={false}
                value={value}></InputNumber>
              )}
              {attr && attr.type === 'int' && (
                <InputNumber className='inputint fullwidth' onChange={(e) => this.onChangeAttr(attr,e.target.value)}
                mode="decimal" useGrouping={false} value={value}></InputNumber>
              )}
              {attr && attr.type === 'year' && (
                <InputNumber className='inputint fullwidth' onChange={(e) => this.onChangeAttr(attr,e.target.value)}
                min={1900} max={2100} mode="decimal" useGrouping={false} value={value}></InputNumber>
              )}
              {attr && attr.type === 'date' && (
                <Calendar appendTo={document.body} onChange={(e) => this.onChangeAttr(attr,e.target.value,true)}
                dateFormat="yy-mm-dd" value={new Date(value)} className='fullwidth'></Calendar>
              )}
              {attr && attr.type === 'enum' && (
                <Dropdown className='fullwidth' appendTo={document.body} onChange={(e) => this.onChangeAttr(attr,e.value)} options={attr.value.split(' ')} value={value}></Dropdown>
              )}
              </div>
            </div>
          </div>
          )})}
        </div>);
    }
}
