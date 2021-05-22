export function pad(number) {
    if (number < 10) {
        return '0' + number;
    }
    return number;
}

export const find_value_of_attribute = (name,lst) => {
    console.log("find value of attribute",name, lst);
    for(var i in lst) {
        var a=lst[i];
        if(a && a.name == name) {
            return a.value;
        }
    }
    return '';
}

export const attribute_by_name = (attrs) =>{
    console.log("converting attributes to by-name-hash ",attrs);
    var retval={};
    attrs.map((a) => {
        retval[a.name]=a;
    });
    return retval;
}

export const create_attribute_from_template = (item) => {
    var def=item.value;
    if(item.type === 'enum') {
        def=item.value.split(' ')[0];
    }
    else if(item.type == 'int') {
        def=0;
    }
    else if(item.type =='year') {
        def=2000;
    }
    else if(item.type == 'number') {
        def=0.0;
    }
    return {
        id: -1,
        name: item.name,
        type: item.type,
        value: def
    }
}

export const create_attributes_from_template = (lst) => {
    return lst.map((item) => create_attribute_from_template(item));
}

export const format_date = (dt) => {
    if(!dt.getDate) dt=new Date(dt);
    return dt.getFullYear() +
        '-' + pad(dt.getMonth() + 1) +
        '-' + pad(dt.getDate());    
}