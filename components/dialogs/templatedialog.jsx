import React from 'react';
import { api_misc, ap_misc } from "../api.js";
import { Button } from 'primereact/button';
import { Dialog } from 'primereact/dialog';
import { Dropdown } from 'primereact/dropdown';
import { Checkbox } from 'primereact/checkbox';
import { InputText } from 'primereact/inputtext';
import { InputNumber } from 'primereact/inputnumber';

export default class TemplateDialog extends React.Component {
    constructor(props, context) {
        super(props, context);
        this.state = {
            old_status:-1
        }
        this.abortType='template';
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

        console.log('saving ',this.props.value);
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

    onChangeEl = (name,value) => {
        console.log(name,value);
        var item=Object.assign({},this.props.value);
        switch(name) {
        case 'name': item[name] = value; break;
        case 'range': item.config = Object.assign({},item.config, { range: parseInt(value)}); break;
        }
        console.log(item);
        if (this.props.onChange) this.props.onChange(item);
    }

    onAdd = () => {
        var newattrs=this.props.value.attributes.map((item)=>item);
        newattrs.push({name:'',value:'',id:-1,type:'string'});
        var item=this.props.value;
        item.attributes=newattrs;
        if (this.props.onChange) this.props.onChange(item);
    }

    onChangeAttr = (action, field,attr,newidx,value) => {
        console.log("onChangeAttr ",action,field,attr.name,value);
        console.log(this.props.value.attributes);
        var newattrs=null;
        if(action == 'delete' || action == 'set') {
            newattrs = this.props.value.attributes.map((item,idx) => {
                if(idx === newidx) {
                    if(action == 'delete') {
                        return null;
                    }
                    else {
                        console.log('copying item ',item);
                        var newitem=Object.assign({remark:{}},item);
                        if(!newitem.remark) newitem.remark={};
                        if(field == "groupBy" || field=="display" || field=="mark") {
                            newitem.remark[field]=value;
                        }
                        else {
                            newitem[field] = value;
                        }
                        return newitem;
                    }
                }
                else {
                    return Object.assign({},item);
                }
            });
            if(action === 'delete') {
                // filter out the potential null values
                newattrs = newattrs.filter(function(item) { return item !== null; });
            }
        }
        else if( (action == 'sortup' && newidx > 0) 
               || (action == 'sortdown' && newidx < (this.props.value.attributes.length-1))) {
            newattrs=this.props.value.attributes.slice();
            if(action == "sortup") {
                // reinsert the item at idx - 1
                var item = newattrs.splice(newidx,1);
                newattrs.splice(newidx-1,0,item[0]);
            }
            else {
                // reinsert the item at idx+1, because we insert after
                // the current item
                var item = newattrs.splice(newidx,1);
                newattrs.splice(newidx+1,0,item[0]);
            }
        }
        if(newattrs !== null) {
            var item=this.props.value;
            item.attributes=newattrs;
            console.log("attributes are now ",newattrs);
            if (this.props.onChange) this.props.onChange(item);
        }
    }

    onDeleteDialog = (event) => {
        if(confirm('Are you sure you want to delete template '+ this.props.value.name + "? This action cannot be undone!")) {
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
            })
    
        }
    }

    render() {
        var footer=(<div>
        <Button label="Add" icon="pi pi-plus" className="p-button-raised p-button-text" onClick={this.onAdd} />
        <Button label="Cancel" icon="pi pi-times" className="p-button-warning p-button-raised p-button-text" onClick={this.onCancelDialog} />
        <Button label="Save" icon="pi pi-check" className="p-button-raised" onClick={this.onCloseDialog} />
</div>);
        if(this.props.value.id >0) {
            footer=(<div>
                <Button label="Add" icon="pi pi-plus" className="p-button-raised p-button-text" onClick={this.onAdd} />
                <Button label="Remove" icon="pi pi-trash" className="p-button-danger p-button-raised p-button-text" onClick={this.onDeleteDialog} />
                <Button label="Cancel" icon="pi pi-times" className="p-button-warning p-button-raised p-button-text" onClick={this.onCancelDialog} />
                <Button label="Save" icon="pi pi-check" className="p-button-raised" onClick={this.onCloseDialog} />
</div>);
        }

        const types=[
            {name: 'Text', value: 'string'},
            {name: 'Number', value: 'number'},
            {name: 'Integer', value: 'int'},
            {name: 'Year', value: 'year'}, // four digit value
            {name: 'Date', value: 'date' }, // ISO field
            {name: 'Enum', value: 'enum' }, // enumeration
            {name: 'BYear', value: 'byear' }, // calculated field
            {name: 'Category', value: 'category'}, // calculated field
            {name: 'Checkbox', value: 'check' }, // yes/no checkbox
        ];

        const markvalues = [
            { name: 'None', value: 'mark-none'},
            { name: 'Red', value: 'mark-red'},
            { name: 'Green', value: 'mark-green' },
            { name: 'Blue', value: 'mark-blue' },
        ];

        const ranges=[
            {name: 'Day', value: 1},
            {name: 'Month', value: 31},
            {name: 'Quarter', value: 92},
            {name: 'Half Year', value: 183},
            {name: 'Year', value: 366}
        ];
        var range= this.props.value.config && this.props.value.config.range ? parseInt(this.props.value.config.range) : 1;

        return (<Dialog header="Edit Template" position="center" visible={this.props.display} style={{ width: '50vw' }} modal={true} footer={footer} onHide={this.onCancelDialog}>
      <div>
        <label>Name</label>
        <div className='input'>
            <InputText name='name' value={this.props.value.name} onChange={(e)=>this.onChangeEl('name',e.target.value)} placeholder='Name'/>
        </div>
      </div>
      <div>
        <label>Presence Range</label>
        <div className='input'>
          <Dropdown appendTo={document.body} name='range' optionLabel="name" optionValue="value" value={range} options={ranges} placeholder="Range" onChange={(e) => this.onChangeEl('range', e.target.value)}/>
        </div>
      </div>
      {this.props.value.attributes && this.props.value.attributes.map((attr,idx) => (
          <div className='attribute' key={idx}>
          <label>Attribute: <InputText name={'name'+idx} value={attr.name} onChange={(e) => this.onChangeAttr('set','name', attr,idx,e.target.value)} placeholder='Name'/></label>
          <div className='inputs'>
            <div className='input'>
              <Dropdown appendTo={document.body} name={'type'+idx} optionLabel="name" optionValue="value" value={attr.type} options={types} placeholder="Type" onChange={(e) => this.onChangeAttr('set', 'type', attr,idx,e.target.value)}/>            
            </div>
            <div className='input'>
              {attr.type != 'check' && (<InputText name={'default'+idx} value={attr.value} onChange={(e) => this.onChangeAttr('set','value', attr,idx,e.target.value)} placeholder='Default'/>)}
              {attr.type == 'check' && (<Dropdown appendTo={document.body} name={'mark' + idx} optionLabel="name" optionValue="value" options={markvalues} value={attr.remark ? attr.remark.mark : ''} onChange={(e) => this.onChangeAttr('set', 'mark', attr, idx, e.target.value)} />)}
            </div>
            <div className='input'>
              <Checkbox inputId={'group'+idx} checked={attr.remark && attr.remark.groupBy} onChange={(e) => this.onChangeAttr('set','groupBy', attr,idx,e.checked)}/>
              <label htmlFor={'group'+idx}>Grp</label>
            </div>
            <div className='input'>
              <Checkbox inputId={'display'+idx} checked={attr.remark && attr.remark.display} onChange={(e) => this.onChangeAttr('set','display', attr,idx,e.checked)}/>
              <label htmlFor={'group'+idx}>Display</label>
            </div>
            <div className='actions'>
              <i className="pi pi-trash pi-mr-2" onClick={(e) => this.onChangeAttr('delete','', attr,idx,'')}></i>
              <i className="pi pi-angle-up pi-mr-2" onClick={(e) => this.onChangeAttr('sortup','', attr,idx,'')}></i>
              <i className="pi pi-angle-down pi-mr-2" onClick={(e) => this.onChangeAttr('sortdown','', attr,idx,'')}></i>
            </div>
          </div>
        </div>
      ))}
</Dialog>
);
    }
}

