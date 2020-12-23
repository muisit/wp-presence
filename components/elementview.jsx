import React from 'react';
import { api_list, api_misc } from "./api.js";
import { Dropdown } from 'primereact/dropdown';
import { InputText } from 'primereact/inputtext';
import { InputNumber } from 'primereact/inputnumber';
import { Calendar } from 'primereact/calendar';

export default class ElementView extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.abortType='frontend';
    }

    onChangeAttr = (attr,value) => {
        var newitem=Object.assign({},this.props.item);
        newitem[attr.name]=value;
        if (this.props.onChange) this.props.onChange(newitem);
    }

    render() {
        return (<div>
              <h4>{this.props.item.name}</h4>
              {this.props.template.attributes && this.props.template.attributes.map((attr,idx) => (
          <div className='attribute' key={idx}>
            <label>{attr.name}</label>
            <div className='inputs'>
              <div className='input'>
              {attr && attr.type === 'string' && (
                <InputText className="fullwidth" value={this.props.item[attr.name]} onChange={(e) => this.onChangeAttr(attr,e.target.value)}/>
              )}
              {attr && attr.type === 'number' && (
                <InputNumber className='inputint fullwidth' onChange={(e) => this.onChangeAttr(attr,e.target.value)}
                mode="decimal" inputMode='decimal' minFractionDigits={1} maxFractionDigits={5} min={0} useGrouping={false}
                value={this.props.item[attr.name]}></InputNumber>
              )}
              {attr && attr.type === 'int' && (
                <InputNumber className='inputint fullwidth' onChange={(e) => this.onChangeAttr(attr,e.target.value)}
                mode="decimal" useGrouping={false} value={this.props.item[attr.name]}></InputNumber>
              )}
              {attr && attr.type === 'year' && (
                <InputNumber className='inputint fullwidth' onChange={(e) => this.onChangeAttr(attr,e.target.value)}
                min={1900} max={2100} mode="decimal" useGrouping={false} value={this.props.item[attr.name]}></InputNumber>
              )}
              {attr && attr.type === 'date' && (
                <Calendar appendTo={document.body} onChange={(e) => this.onChangeAttr(attr,e.target.value)}
                dateFormat="yy-mm-dd" value={new Date(this.props.item[attr.name])} className='fullwidth'></Calendar>
              )}
              {attr && attr.type === 'enum' && (
                <Dropdown className='fullwidth' appendTo={document.body} onChange={(e) => this.onChangeAttr(attr,e.value)} options={attr.value.split(' ')} value={this.props.item[attr.name]}></Dropdown>
              )}
              </div>
            </div>
          </div>
          ))}              
          <div className='attribute'>
            <label>Name</label>
            <div className='inputs'>
              <div className='input'>
                <InputText className="fullwidth" value={this.props.item.name} onChange={(e) => this.onChangeAttr({name:'name'},e.target.value)}/>
              </div>
            </div>
          </div>
        </div>);
    }
}
