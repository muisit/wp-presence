import React from 'react';
import { api_misc, ap_misc } from "../api.js";
import { Button } from 'primereact/button';
import { Dialog } from 'primereact/dialog';
import { Dropdown } from 'primereact/dropdown';
import { InputText } from 'primereact/inputtext';
import { InputNumber } from 'primereact/inputnumber';
import { Calendar } from 'primereact/calendar';

export default class GenericDialog extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.state = {
            old_status:-1
        }
        this.abortType=(this.props.value && this.props.value.type) || 'none';

        this.enums={};
        for(var i in this.props.template.attributes) {
            var attr=this.props.template.attributes[i];
            if(attr.type=='enum') {
                this.enums[attr.name] = attr.value.split(' ');
            }
        }
    }

    loading = (state) => {
        if(this.props.onLoad) this.props.onLoad(state);
    }

    close = () => {
        if(this.props.onClose) this.props.onClose();
    }

    save = (item) => {
        if(this.props.onSave) this.props.onSave(item);
        this.close();
    }

    delete = (item) => {
        if(this.props.onDelete) this.props.onDelete(item);
        this.close();
    }

    onCloseDialog = (event) => {
        this.loading(true);

        api_misc(this.abortType,'item','save',this.props.value)
            .then((json) => {
                this.loading(false);
                this.save(this.props.value);
            })
            .catch((err) => {
                console.log("caught error ",err);
                if(err.response.data.messages && err.response.data.messages.length) {
                    var txt="";
                    for(var i=0;i<err.response.data.messages.length;i++) {
                       txt+=err.response.data.messages[i]+"\r\n";
                    }
                    alert(txt);
                }
                else {
                    alert('Error storing the data. Please try again');
                }
            });
    }

    onCancelDialog = (event) => {
        this.close();
    }    

    onChangeEl = (event) => {
        if(!event.target || !event.target.value) return;
        var item=Object.assign({},this.props.value);
        switch(event.target.name) {
        case 'name': item[event.target.name] = event.target.value; break;
        }
        if (this.props.onChange) this.props.onChange(item);
    }

    onChangeAttr = (field,attr,newidx,e) => {
        var value = e.value || e.target.value;
        console.log("onChangeAttr ",field,attr.name,value);
        console.log(this.props.value.attributes);
        var newattrs = this.props.value.attributes.map((item,idx) => {
            if(idx === newidx) {
                console.log("setting value for index "+newidx + " named " + item.name);
                var newitem=Object.assign({},item);
                if(attr.type=='date') {
                    var dt=new Date(value);
                    value = dt.getFullYear() + "-" + ((dt.getMonth() < 9) ? '0' : '') + (dt.getMonth()+1) + "-" + ((dt.getDate() < 10) ? '0':'') + dt.getDate();
                }
                newitem[field] = value;
                return newitem;
            }
            return Object.assign({},item);
        });
        if(newattrs !== null) {
            var item=this.props.value;
            item.attributes=newattrs;
            console.log("attributes are now ",newattrs);
            if (this.props.onChange) this.props.onChange(item);
        }
    }

    onDeleteDialog = (event) => {
        if(confirm('Are you sure you want to delete '+ this.props.value.type + " " + this.props.value.name + "? This action cannot be undone!")) {
            this.loading(true);
            api_misc(this.abortType,'item','delete',{ id: this.props.value.id})
            .then((json) => {
                this.loading(false);
                this.delete();
            })
            .catch((err) => {
                if(err.response.data.messages && err.response.data.messages.length) {
                    var txt="";
                    for(var i=0;i<err.response.data.messages.length;i++) {
                        txt+=err.response.data.messages[i]+"\r\n";
                    }
                    alert(txt);
                }
                else {
                    alert('Error removing the data. Please try again');
                }
            });    
        }
    }

    render() {
        if(!this.props.value) {
            return (<div></div>);
        }
        var footer=(<div>
        <Button label="Cancel" icon="pi pi-times" className="p-button-warning p-button-raised p-button-text" onClick={this.onCancelDialog} />
        <Button label="Save" icon="pi pi-check" className="p-button-raised" onClick={this.onCloseDialog} />
</div>);
        if(this.props.value.id >0 && ! this.props.onlyAdd) {
            footer=(<div>
                <Button label="Remove" icon="pi pi-trash" className="p-button-danger p-button-raised p-button-text" onClick={this.onDeleteDialog} />
                <Button label="Cancel" icon="pi pi-times" className="p-button-warning p-button-raised p-button-text" onClick={this.onCancelDialog} />
                <Button label="Save" icon="pi pi-check" className="p-button-raised" onClick={this.onCloseDialog} />
</div>);
        }

        return (<Dialog header={"Edit " + this.props.value.type} position="center" visible={this.props.display} style={{ width: this.props.width || '50vw' }} modal={true} footer={footer} onHide={this.onCancelDialog} className={"generic " + this.props.value.type}>
      <div className='attribute'>
        <label>Name</label>
        <div className='inputs'>
          <div className='input'>
              <InputText name='name' value={this.props.value.name} onChange={this.onChangeEl} placeholder='Name'/>
          </div>
        </div>
      </div>
      {this.props.value.attributes && this.props.value.attributes.map((attr,idx) => (
          <div className='attribute' key={idx}>
          <label>{attr.name}</label>
          <div className='inputs'>
            <div className='input'>
              {attr && attr.type === 'string' && (
                <InputText value={attr.value} onChange={(e) => this.onChangeAttr('value', attr,idx,e)}/>
              )}
              {attr && attr.type === 'number' && (
                <InputNumber className='inputint' onChange={(e) => this.onChangeAttr('value', attr,idx,e)}
                mode="decimal" inputMode='decimal' minFractionDigits={1} maxFractionDigits={5} min={0} useGrouping={false}
                value={parseFloat(attr.value)}></InputNumber>
              )}
              {attr && attr.type === 'int' && (
                <InputNumber className='inputint' onChange={(e) => this.onChangeAttr('value', attr,idx,e)}
                mode="decimal" useGrouping={false} value={parseInt(attr.value)}></InputNumber>
              )}
              {attr && attr.type === 'year' && (
                <InputNumber className='inputint' onChange={(e) => this.onChangeAttr('value', attr,idx,e)}
                min={1900} max={2100} mode="decimal" useGrouping={false} value={parseInt(attr.value)}></InputNumber>
              )}
              {attr && attr.type === 'date' && (
                <Calendar appendTo={document.body} onChange={(e) => this.onChangeAttr('value', attr,idx,e)}
                dateFormat="yy-mm-dd" value={new Date(attr.value)}></Calendar>
              )}
              {attr && attr.type === 'enum' && (
                <Dropdown appendTo={document.body} onChange={(e) => this.onChangeAttr('value', attr,idx,e)} options={this.enums[attr.name]} value={attr.value}></Dropdown>
              )}
            </div>
          </div>
        </div>
      ))}
</Dialog>
);
    }
}

